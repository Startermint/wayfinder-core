local job = redis.call('lpop', KEYS[1])

if not job then
    return nil
end

local payload = cjson.decode(job)
payload['attempts'] = tonumber(payload['attempts'] or 0) + 1

local reserved = cjson.encode(payload)
redis.call('zadd', KEYS[2], ARGV[1], reserved)

return reserved

