local jobs = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1], 'LIMIT', 0, ARGV[2])

for _, job in ipairs(jobs) do
    if redis.call('zrem', KEYS[1], job) == 1 then
        redis.call('rpush', KEYS[2], job)
    end
end

return #jobs

