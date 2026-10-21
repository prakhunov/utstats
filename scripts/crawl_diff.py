#!/usr/bin/env python3
"""Fetch the same routes from the PHP 5.5 baseline (:8055) and the PHP 8.5 port (:8080)
and report which pages differ. Usage:

    scripts/crawl_diff.py routes                 # (re)generate .verify/routes.txt from the DB
    scripts/crawl_diff.py [--grep SUBSTR] [-v]   # diff every route (or those containing SUBSTR)

Diffs for differing routes land in .verify/diff/<n>.diff.
"""
import difflib
import re
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
VERIFY = ROOT / ".verify"
ROUTES = VERIFY / "routes.txt"
OLD, NEW = "http://127.0.0.1:8055", "http://127.0.0.1:8080"

# Per-request noise: random header art, timers, "last visit" cookies.
NOISE = [
    (re.compile(r"char\d+\.jpg"), "charN.jpg"),
    (re.compile(r"(?i)(generated|processed) in [\d.]+ ?(seconds|sec|s)"), r"\1 in N"),
]


def sql(query: str) -> list[list[str]]:
    out = subprocess.run(
        ["podman", "compose", "exec", "-T", "db", "sh", "-c",
         'mariadb -N -B -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "$1"', "_", query],
        cwd=ROOT, capture_output=True, text=True, check=True).stdout
    return [line.split("\t") for line in out.splitlines() if line]


def generate_routes() -> None:
    static = ["/", "/?p=recent", "/?p=rank", "/?p=servers", "/?p=players", "/?p=maps", "/?p=totals",
              "/?p=credits", "/?p=help", "/?p=watchlist", "/?p=players&page=3", "/?p=maps&page=2",
              "/?p=psearch&name=a", "/?p=players&filter=name&sort=DESC", "/?p=maps&sort=games",
              "/?p=recent&year=2024&month=6"]
    # A few matches per gametype, newest and oldest.
    mids = sql("SELECT id FROM (SELECT id, gid, ROW_NUMBER() OVER (PARTITION BY gid ORDER BY id DESC) rn FROM uts_match) t WHERE rn <= 2"
               " UNION SELECT MIN(id) FROM uts_match GROUP BY gid")
    pids = sql("(SELECT pid FROM uts_player GROUP BY pid ORDER BY COUNT(*) DESC LIMIT 5)"
               " UNION (SELECT id FROM uts_pinfo ORDER BY id DESC LIMIT 3)")
    gids = sql("SELECT id FROM uts_games")
    maps = sql("SELECT mapfile FROM uts_match GROUP BY mapfile ORDER BY COUNT(*) DESC LIMIT 4")
    servers = sql("SELECT serverip FROM uts_match GROUP BY serverip ORDER BY COUNT(*) DESC LIMIT 2")
    routes = list(static)
    for (mid,) in mids:
        routes += [f"/?p=match&mid={mid}", f"/?p=report&id={mid}&rtype=bbcode"]
    first_pid = pids[0][0]
    for (pid,) in pids:
        routes += [f"/?p=pinfo&pid={pid}", f"/?p=pexplrank&pid={pid}&gid={gids[0][0]}"]
    routes += [f"/?p=matchp&mid={mids[0][0]}&pid={first_pid}"]
    for (gid,) in gids:
        routes += [f"/?p=ext_rank&gid={gid}", f"/?p=recent&gid={gid}"]
    for (m,) in maps:
        routes += [f"/?p=minfo&map={urllib.request.quote(m)}"]
    for (ip,) in servers:
        routes += [f"/?p=sinfo&serverip={urllib.request.quote(ip)}"]
    routes += [f"/api/{name}" for name in (
        "list_players.php", f"match_details.php?matchid={mids[0][0]}", f"match_summary.php?matchid={mids[0][0]}",
        "player_name_suggestions.php?player=a", "player_stats.php?player=1&limit=5",
        "map_stats.php?player=1", "ctf_stats.php?player=1", "kills_deaths.php?player=1",
        "misc_avg_stats.php?player=1", "misc_sum_stats.php?player=1", "spree_stats.php?player=1",
        "top_by_player.php?type=frags", "topper_count_stats.php?type=frags", "topper_stats.php?type=frags",
        "win_info.php?player=1")]
    routes += [f"/pic.php/1/{first_pid}/0/.png"]
    VERIFY.mkdir(exist_ok=True)
    ROUTES.write_text("\n".join(dict.fromkeys(routes)) + "\n")
    print(f"wrote {len(routes)} routes to {ROUTES}")


def fetch(base: str, route: str) -> tuple[int, bytes]:
    try:
        with urllib.request.urlopen(base + route, timeout=120) as r:
            return r.status, r.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()


def normalize(body: bytes) -> list[str]:
    text = body.decode("utf-8", "replace")
    for pattern, repl in NOISE:
        text = pattern.sub(repl, text)
    return text.splitlines(keepends=True)


def main(args: list[str]) -> int:
    if args[:1] == ["routes"]:
        generate_routes()
        return 0
    verbose = "-v" in args
    needle = args[args.index("--grep") + 1] if "--grep" in args else ""
    routes = [r for r in ROUTES.read_text().split() if needle in r]
    diffdir = VERIFY / "diff"
    diffdir.mkdir(parents=True, exist_ok=True)
    for old in diffdir.glob("*.diff"):
        old.unlink()
    bad = 0
    for n, route in enumerate(routes):
        (s_old, b_old), (s_new, b_new) = fetch(OLD, route), fetch(NEW, route)
        if route.startswith("/pic.php"):
            same = s_old == s_new and b_new[:4] == b_old[:4]
            lines = [] if same else [f"status {s_old} vs {s_new}, magic {b_old[:4]!r} vs {b_new[:4]!r}\n"]
        else:
            lines = list(difflib.unified_diff(normalize(b_old), normalize(b_new), "php55", "php85", n=1))
            same = s_old == s_new and not lines
        if same:
            if verbose:
                print(f"  ok   {route}")
            continue
        bad += 1
        out = diffdir / f"{n:03d}.diff"
        out.write_text(f"# {route}\n# status {s_old} -> {s_new}\n" + "".join(lines))
        print(f"  DIFF {route}  (status {s_old}->{s_new}, {len(lines)} diff lines) -> {out.relative_to(ROOT)}")
    print(f"{len(routes) - bad}/{len(routes)} routes identical")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
