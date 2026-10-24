#!/bin/sh
# Request the pages whose all-time aggregates are cached (db_all_cached), so the expensive
# recompute after an import or TTL expiry happens here, not on a visitor/crawler request.
# Runs as the utstats user (utstats-warm.timer, and after every importer run).
for path in / '/?p=players' '/?p=rank' '/?p=maps' '/?p=recent'; do
    curl -sk -o /dev/null --max-time 120 --resolve utstats.ut.banko.gg:443:127.0.0.1 "https://utstats.ut.banko.gg$path"
done
