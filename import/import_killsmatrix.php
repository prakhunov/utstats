<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

$sql_killmatrix = "SELECT 	col2 AS killer, 
										col4 AS killed, 
										COUNT(*) AS kills 
							FROM 		uts_temp_$uid 
							WHERE 	col1 = 'kill' 
								OR		col1 = 'teamkill'
							GROUP BY col2, col4";

$q_killmatrix = db_all($sql_killmatrix);
foreach ($q_killmatrix as $r_killmatrix) {

    $sql =	"	INSERT
						INTO		uts_killsmatrix
						SET		matchid 	=	?,
									killer	=	?,
									victim	=	?,
									kills		=	?;";
    db_exec($sql, [$matchid, $r_killmatrix['killer'], $r_killmatrix['killed'], $r_killmatrix['kills']]);

}
