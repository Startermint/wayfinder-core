<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Database;

use PHPUnit\Framework\TestCase;
use Wayfinder\Database\Model;
use Wayfinder\Tests\Concerns\UsesDatabase;

final class ModelTest extends TestCase
{
    use UsesDatabase;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testModelHydratesTypedObjectInsteadOfReturningArray(): void
    {
        $this->db->insert('users', [
            'email' => 'ada@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        $user = TestUser::find(1);

        self::assertInstanceOf(TestUser::class, $user);
        self::assertSame('ada@example.com', $user?->email);
        self::assertSame(1, $user?->is_admin);
        self::assertTrue($user?->exists() ?? false);
    }

    public function testModelSupportsSimpleCrudOperations(): void
    {
        $created = TestUser::create([
            'email' => 'grace@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);

        self::assertInstanceOf(TestUser::class, $created);
        self::assertSame('grace@example.com', $created->email);
        self::assertSame(1, $created->getKey());

        $fetched = TestUser::where('email', 'grace@example.com')->first();
        self::assertInstanceOf(TestUser::class, $fetched);
        self::assertSame($created->getKey(), $fetched->getKey());

        $created->update([
            'email' => 'hopper@example.com',
            'is_admin' => 1,
        ]);

        $updated = TestUser::find($created->getKey());
        self::assertSame('hopper@example.com', $updated?->email);
        self::assertSame(1, $updated?->is_admin);

        self::assertTrue($created->delete());
        self::assertNull(TestUser::find($created->getKey()));
    }

    public function testModelSupportsBulkCreateHelpers(): void
    {
        $created = TestUser::createMany([
            [
                'email' => 'bulk-1@example.com',
                'password' => 'secret',
                'is_admin' => 0,
            ],
            [
                'email' => 'bulk-2@example.com',
                'password' => 'secret',
                'is_admin' => 1,
            ],
        ]);

        self::assertCount(2, $created);
        self::assertContainsOnlyInstancesOf(TestUser::class, $created);
        self::assertSame(['bulk-1@example.com', 'bulk-2@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $created,
        ));
        self::assertSame(2, TestUser::count());
        self::assertSame([], TestUser::createMany([]));
    }

    public function testModelSupportsConditionalBulkDelete(): void
    {
        TestUser::createMany([
            [
                'email' => 'delete-1@example.com',
                'password' => 'secret',
                'is_admin' => 0,
            ],
            [
                'email' => 'delete-2@example.com',
                'password' => 'secret',
                'is_admin' => 1,
            ],
            [
                'email' => 'delete-3@example.com',
                'password' => 'secret',
                'is_admin' => 1,
            ],
        ]);

        $deleted = TestUser::deleteWhere('is_admin', 1);

        self::assertSame(2, $deleted);
        self::assertSame(['delete-1@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            TestUser::oldest('id')->get(),
        ));
    }

    public function testModelAllReturnsTypedCollection(): void
    {
        $this->db->insert('users', [
            'email' => 'alpha@example.com',
            'password' => 'one',
            'is_admin' => 0,
        ]);
        $this->db->insert('users', [
            'email' => 'beta@example.com',
            'password' => 'two',
            'is_admin' => 1,
        ]);

        $users = TestUser::query()->orderBy('id')->get();

        self::assertCount(2, $users);
        self::assertContainsOnlyInstancesOf(TestUser::class, $users);
        self::assertSame(['alpha@example.com', 'beta@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $users,
        ));
    }

    public function testModelQueryAllAliasReturnsTypedCollection(): void
    {
        $this->db->insert('users', [
            'email' => 'gamma@example.com',
            'password' => 'one',
            'is_admin' => 0,
        ]);

        $users = TestUser::query()->orderBy('id')->all();

        self::assertCount(1, $users);
        self::assertContainsOnlyInstancesOf(TestUser::class, $users);
        self::assertSame('gamma@example.com', $users[0]->email);
    }

    public function testModelQuerySupportsNullPredicatesAndPaginationHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'null-model@example.com',
            'password' => 'one',
            'is_admin' => 0,
            'nickname' => null,
        ]);
        $this->db->insert('users', [
            'email' => 'named-model@example.com',
            'password' => 'two',
            'is_admin' => 1,
            'nickname' => 'named',
        ]);

        $nullUsers = TestUser::query()->whereNull('nickname')->all();
        $namedUsers = TestUser::query()->whereNotNull('nickname')->forPage(1, 1)->all();

        self::assertCount(1, $nullUsers);
        self::assertSame('null-model@example.com', $nullUsers[0]->email);
        self::assertCount(1, $namedUsers);
        self::assertSame('named-model@example.com', $namedUsers[0]->email);
        self::assertSame(1, TestUser::query()->whereNotNull('nickname')->sum('is_admin'));
    }

    public function testModelSupportsNullAndOrWherePredicatesWithoutDroppingToQuery(): void
    {
        $this->db->insert('users', [
            'email' => 'null-static@example.com',
            'password' => 'one',
            'is_admin' => 0,
            'nickname' => null,
        ]);
        $this->db->insert('users', [
            'email' => 'admin-static@example.com',
            'password' => 'two',
            'is_admin' => 1,
            'nickname' => 'admin',
        ]);
        $this->db->insert('users', [
            'email' => 'member-static@example.com',
            'password' => 'three',
            'is_admin' => 0,
            'nickname' => 'member',
        ]);

        $nullUsers = TestUser::whereNull('nickname')->get();
        $namedUsers = TestUser::whereNotNull('nickname')->oldest('id')->get();
        $matched = TestUser::where('email', 'null-static@example.com')
            ->orWhere('is_admin', 1)
            ->oldest('id')
            ->get();

        self::assertCount(1, $nullUsers);
        self::assertSame('null-static@example.com', $nullUsers[0]->email);
        self::assertSame(['admin-static@example.com', 'member-static@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $namedUsers,
        ));
        self::assertSame(['null-static@example.com', 'admin-static@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $matched,
        ));
    }

    public function testModelSupportsKeyAndConvenienceLookupHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'lookup@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        $user = TestUser::whereKey(1)->first();
        $same = TestUser::firstWhere('email', 'lookup@example.com');
        $required = TestUser::findOrFail(1);

        self::assertInstanceOf(TestUser::class, $user);
        self::assertSame('lookup@example.com', $same?->email);
        self::assertSame(1, $required->getKey());
    }

    public function testModelSupportsMembershipAndBulkLookupHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'member-1@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);
        $this->db->insert('users', [
            'email' => 'member-2@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);
        $this->db->insert('users', [
            'email' => 'member-3@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        $selected = TestUser::whereIn('id', [1, 3])->oldest('id')->get();
        $excluded = TestUser::whereNotIn('id', [2])->oldest('id')->get();
        $many = TestUser::findMany([2, 3]);

        self::assertSame(['member-1@example.com', 'member-3@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $selected,
        ));
        self::assertSame(['member-1@example.com', 'member-3@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $excluded,
        ));
        self::assertCount(2, $many);
        self::assertContainsOnlyInstancesOf(TestUser::class, $many);
    }

    public function testModelSupportsOrderingAndCollectionRetrievalHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'c@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);
        $this->db->insert('users', [
            'email' => 'a@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);
        $this->db->insert('users', [
            'email' => 'b@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        $latest = TestUser::latest()->get();
        $oldest = TestUser::oldest()->get();
        $admins = TestUser::get('is_admin', 1);
        $ordered = TestUser::orderBy('email')->get();

        self::assertSame(['b@example.com', 'a@example.com', 'c@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $latest,
        ));
        self::assertSame(['c@example.com', 'a@example.com', 'b@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $oldest,
        ));
        self::assertCount(2, $admins);
        self::assertSame(['a@example.com', 'b@example.com', 'c@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $ordered,
        ));
    }

    public function testModelSupportsPluckAndValueHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'pluck-1@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);
        $this->db->insert('users', [
            'email' => 'pluck-2@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        self::assertSame(['pluck-1@example.com', 'pluck-2@example.com'], TestUser::oldest()->pluck('email'));
        self::assertSame('pluck-1@example.com', TestUser::oldest()->value('email'));
        self::assertSame(['pluck-1@example.com', 'pluck-2@example.com'], TestUser::pluck('email'));
        self::assertSame('pluck-1@example.com', TestUser::value('email'));
    }

    public function testModelSupportsPaginationHelpers(): void
    {
        foreach ([
            'page-1@example.com',
            'page-2@example.com',
            'page-3@example.com',
        ] as $index => $email) {
            $this->db->insert('users', [
                'email' => $email,
                'password' => 'secret',
                'is_admin' => $index % 2,
            ]);
        }

        $plainPage = TestUser::paginate(1, 2);
        $firstPage = TestUser::oldest()->paginate(1, 2);
        $secondPage = TestUser::oldest()->paginate(2, 2);

        self::assertCount(2, $plainPage->items());
        self::assertSame(3, $plainPage->total());
        self::assertCount(2, $firstPage->items());
        self::assertContainsOnlyInstancesOf(TestUser::class, $firstPage->items());
        self::assertSame(['page-1@example.com', 'page-2@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $firstPage->items(),
        ));
        self::assertSame(3, $firstPage->total());
        self::assertSame(2, $firstPage->lastPage());
        self::assertTrue($firstPage->hasNextPage());
        self::assertSame(2, $firstPage->nextPage());
        self::assertSame(['page-3@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $secondPage->items(),
        ));
        self::assertSame(2, $secondPage->currentPage());
        self::assertSame(3, $secondPage->from());
        self::assertSame(3, $secondPage->to());
    }

    public function testModelSupportsSliceHelpers(): void
    {
        foreach ([
            'slice-1@example.com',
            'slice-2@example.com',
            'slice-3@example.com',
            'slice-4@example.com',
        ] as $email) {
            $this->db->insert('users', [
                'email' => $email,
                'password' => 'secret',
                'is_admin' => 0,
            ]);
        }

        $limited = TestUser::oldest()->take(2)->get();
        $offset = TestUser::oldest()->skip(1)->take(2)->get();
        $paged = TestUser::forPage(2, 2)->oldest('id')->get();

        self::assertSame(['slice-1@example.com', 'slice-2@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $limited,
        ));
        self::assertSame(['slice-2@example.com', 'slice-3@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $offset,
        ));
        self::assertSame(['slice-3@example.com', 'slice-4@example.com'], array_map(
            static fn (TestUser $user): string => (string) $user->email,
            $paged,
        ));
    }

    public function testModelSupportsAggregateAndExistenceHelpers(): void
    {
        $this->db->insert('users', [
            'email' => 'aggregate-1@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);
        $this->db->insert('users', [
            'email' => 'aggregate-2@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);
        $this->db->insert('users', [
            'email' => 'aggregate-3@example.com',
            'password' => 'secret',
            'is_admin' => 1,
        ]);

        self::assertSame(3, TestUser::count());
        self::assertTrue(TestUser::existsWhere('email', 'aggregate-2@example.com'));
        self::assertFalse(TestUser::existsWhere('email', 'missing@example.com'));
        self::assertSame(2, TestUser::sum('is_admin'));
        self::assertSame(2 / 3, TestUser::avg('is_admin'));
        self::assertSame(0, TestUser::min('is_admin'));
        self::assertSame(1, TestUser::max('is_admin'));
    }

    public function testModelMakeAndSavePersistNewRecords(): void
    {
        $user = TestUser::make([
            'email' => 'draft@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);

        self::assertFalse($user->exists());

        $user->save();

        self::assertTrue($user->exists());
        self::assertSame('draft@example.com', TestUser::find($user->getKey())?->email);
    }

    public function testModelRefreshAndFreshReturnCurrentState(): void
    {
        $created = TestUser::create([
            'email' => 'refresh@example.com',
            'password' => 'secret',
            'is_admin' => 0,
        ]);

        $this->db->update('users', [
            'email' => 'refreshed@example.com',
        ])->where('id', $created->getKey())->execute();

        $fresh = $created->fresh();
        $created->refresh();

        self::assertSame('refreshed@example.com', $fresh?->email);
        self::assertSame('refreshed@example.com', $created->email);
    }

    public function testModelSupportsFirstOrCreateAndUpdateOrCreate(): void
    {
        $created = TestUser::firstOrCreate([
            'email' => 'first-or-create@example.com',
        ], [
            'password' => 'secret',
            'is_admin' => 0,
        ]);

        $again = TestUser::firstOrCreate([
            'email' => 'first-or-create@example.com',
        ], [
            'password' => 'different',
            'is_admin' => 1,
        ]);

        $updated = TestUser::updateOrCreate([
            'email' => 'first-or-create@example.com',
        ], [
            'is_admin' => 1,
        ]);

        $new = TestUser::updateOrCreate([
            'email' => 'update-or-create@example.com',
        ], [
            'password' => 'secret',
            'is_admin' => 0,
        ]);

        self::assertSame($created->getKey(), $again->getKey());
        self::assertSame($created->getKey(), $updated->getKey());
        self::assertSame(1, $updated->is_admin);
        self::assertSame('update-or-create@example.com', $new->email);
    }

    public function testModelFindOrFailThrowsClearError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(TestUser::class . ' record [999] not found.');

        TestUser::findOrFail(999);
    }
}

final class TestUser extends Model
{
    protected static string $table = 'users';
}
