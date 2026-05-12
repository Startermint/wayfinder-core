redis.call('zrem', KEYS[1], ARGV[1])
redis.call('zadd', KEYS[2], ARGV[2], ARGV[1])

return 1

