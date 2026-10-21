<?php 
if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) die('bla');

$debug = 0; // This enables debug messages

/*
** This function merges two playerss
** @var $mplayer1 : The player to merge to
** @var $mplayer2 : The player who gets merged into mplayer 1
** @return null
*/
function merge_players($mplayer1, $mplayer2) {
    $mplayer1 = (int)$mplayer1;
    $mplayer2 = (int)$mplayer2;
    $mp1name = db_row("SELECT name FROM uts_pinfo WHERE id = $mplayer1");
    $mp2name = db_row("SELECT name FROM uts_pinfo WHERE id = $mplayer2");
    
    db_exec("DELETE FROM uts_pinfo WHERE id = $mplayer2");
    db_exec("UPDATE uts_player SET pid = $mplayer1  WHERE pid = $mplayer2");
    db_exec("UPDATE uts_weaponstats SET pid = $mplayer1  WHERE pid = $mplayer2");
    db_exec("DELETE FROM uts_weaponstats WHERE pid = $mplayer2");
    db_exec("DELETE FROM uts_weaponstats WHERE matchid='0' AND pid = '$mplayer1'");
    
    foreach (db_all("SELECT weapon, SUM(kills) AS kills, SUM(shots) AS shots, SUM(hits) as hits, SUM(damage) as damage, AVG(acc) AS acc FROM uts_weaponstats WHERE pid = '$mplayer1'  GROUP BY weapon") as $r_weaponstats) {
        db_exec("INSERT INTO uts_weaponstats SET matchid='0', pid='$mplayer1',  weapon=?, kills=?, shots=?, hits=?, damage=?, acc=?", [$r_weaponstats['weapon'], $r_weaponstats['kills'], $r_weaponstats['shots'], $r_weaponstats['hits'], $r_weaponstats['damage'], $r_weaponstats['acc']]);
    }
    db_exec("UPDATE uts_match SET firstblood = $mplayer1  WHERE firstblood = $mplayer2");
    db_exec("UPDATE uts_rank SET pid = $mplayer2 WHERE pid= $mplayer1");
    $sql_nrank = "SELECT SUM(time) AS time, pid, gid, AVG(rank) AS rank, AVG(prevrank) AS prevrank, SUM(matches) AS matches FROM uts_rank WHERE pid = $mplayer2 GROUP BY pid, gid";
    foreach (db_all($sql_nrank) as $r_nrank) {
    
        db_exec("INSERT INTO uts_rank SET time = ?, pid = $mplayer1, gid = ?, rank = ?, prevrank = ?, matches = ?", [$r_nrank['time'], $r_nrank['gid'], $r_nrank['rank'], $r_nrank['prevrank'], $r_nrank['matches']]);
    }
    db_exec("DELETE FROM uts_rank WHERE pid = $mplayer2");
}

// If debugmode is on, get start time
if($debug) {
	$time = microtime();
	$time = explode(' ', $time);
	$time = $time[1] + $time[0];
	$begintime = $time;
}

// Print header & links
echo '<P>Player merging tool</P>';
echo "<P><A href=admin.php?key=".$adminkey."&action=plm>Merge ip's</A> - <A href=admin.php?key=".$adminkey."&action=plm&onlyrange=true>Merge ip's, limited on range</A> - <A href=admin.php?key=".$adminkey."&action=plm&manignore=true>Manage ignored ip's</A> - <A href=admin.php?key=".$adminkey."&action=plm&manignore=true&onlyrange=true>Manage ignored ip's - only range</A><BR><BR></P>";

// CASE: MANAGE IGNORED IPS
if(($_GET['manignore'] ?? '') == "true") {
	if(($_POST['submit'] ?? '') == "Remove from ignored list") {
    	echo "<P>Stopped ignore ip's</P>";
        foreach($_POST as $key=>$value) {
            if($key !='submit') {
                $key = (str_replace("_",".",$key));
                $query = "DELETE FROM uts_ignoreips WHERE ip = (INET_ATON(?))";
                db_exec($query, [$key]);
                echo '<br>'.htmlspecialchars($key);
            }
        }
	} else if(($_GET['onlyrange'] ?? '') == "true") {
    	if(($_POST['submit'] ?? '') == "Confirm") {
			echo '<P>Ignored ip\'s in range '.htmlentities($_POST['from']).' to '.htmlentities($_POST['to']).'<br><I>If you want to stop ignoring some ip\'s, because for example you accidently ignored these, check these and press the button at the lower end to confirm this</I></P>';
			echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm&manignore=true" target="_blank">';    		
			
			$from = $_POST['from'];
    		$to = $_POST['to'];
    		
    	    $ignore_ips = db_all("SELECT ip FROM uts_ignoreips WHERE ip >= INET_ATON(?) AND ip <= INET_ATON(?) ORDER BY ip ASC", [$from, $to]);
	
	        if(count($ignore_ips) > 0) {
		        foreach ($ignore_ips as $r_pipcheck) {
		            
		            $playerip = $r_pipcheck['ip'];
		            $trueplayerip = long2ip($playerip);
		            $pidcount = $r_pipcheck['pidcount'] ?? null;
		            
		                echo "<br><input type=checkbox name=$trueplayerip> $trueplayerip";
		                
		                // Query for player names and ids associated to that ip during the cycle
		                $sql_pcheck = "SELECT pi.id, pi.name, pi.country, p.pid, p.ip FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
		                foreach (db_all($sql_pcheck) as $r_pcheck) {
		                    echo '<br><a class="darkhuman" href="admin.php?key='.$adminkey.'&amp;action=pinfo&amp;pid='.$r_pcheck['pid'].'">'.FormatPlayerName($r_pcheck['country'], $r_pcheck['pid'], $r_pcheck['name']).'</a> ';
		                }
		                echo '<br />';
		        }
		        echo '<BR><INPUT TYPE="SUBMIT" VALUE="Remove from ignored list" NAME="submit"></FORM>';
		    } else {
		    	echo "<BR><P>No ignored ip's found</P>";
		    }
    	} else {
    		echo "<P>Ignored ip's in range: Enter range to filter on</P>";
        	echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm&manignore=true&onlyrange=true">';
        	echo '<BR>Ranging from <INPUT TYPE="TEXT" NAME="from" SIZE="20" VALUE="0.0.0.0"> to <INPUT TYPE="TEXT" NAME="to" SIZE="20" VALUE="255.255.255.255">';
    		echo '<BR><BR><INPUT TYPE="SUBMIT" NAME="submit" VALUE="Confirm"></FORM>';
    	}		
	} else {
		echo '<P>Ignored ip\'s<br><I>If you want to stop ignoring some ip\'s, because for example you accidently ignored these, check these and press the button at the lower end to confirm this</I></P>';
		echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm&manignore=true" target="_blank">';
		
        $ignore_ips = db_all("SELECT ip FROM uts_ignoreips ORDER BY ip ASC");
        if(count($ignore_ips) > 0) {
	        foreach ($ignore_ips as $r_pipcheck) {
	            
	            $playerip = $r_pipcheck['ip'];
	            $trueplayerip = long2ip($playerip);
	            $pidcount = $r_pipcheck['pidcount'] ?? null;
	            
	                echo "<br><input type=checkbox name=$trueplayerip> $trueplayerip";
	                
	                // Query for player names and ids associated to that ip during the cycle
	                $sql_pcheck = "SELECT pi.id, pi.name, pi.country, p.pid, p.ip FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
	                foreach (db_all($sql_pcheck) as $r_pcheck) {
	                    echo '<br><a class="darkhuman" href="admin.php?key='.$adminkey.'&amp;action=pinfo&amp;pid='.$r_pcheck['pid'].'">'.FormatPlayerName($r_pcheck['country'], $r_pcheck['pid'], $r_pcheck['name']).'</a> ';
	                }
	                echo '<br />';
	        }
	        echo '<BR><INPUT TYPE="SUBMIT" VALUE="Remove from ignored list" NAME="submit"></FORM>';
	    } else {
	    	echo "<BR><P>No ignored ip's found</P>";
	    }
    }
    
// CASE: IGNORE SUBMITTED IP'S
} else if(($_POST['submit'] ?? '') == "ignore") {
	echo "<P>Ignored ips</P>";
    foreach($_POST as $key=>$value) {
        if($key !='submit') {
            $key = (str_replace("_",".",$key));
            $query = "INSERT INTO uts_ignoreips (ip) VALUES (INET_ATON(?))";
            db_exec($query, [$key]);
            echo '<br>'.htmlspecialchars($key);
        }
    }
    echo "<br>";
    
// CASE: SHOW NICKS WITH GIVEN SHARED IP   
} else if(substr($_POST['submit'] ?? '',0,8) == "merge - ") {
	echo "<P>Merge nicks with shared ip</P>";
	
    $ip = str_replace("_",".",substr($_POST['submit'],8));
    $sql_pipcheck = "SELECT ip, COUNT(DISTINCT pid) AS pidcount FROM uts_player WHERE ip = INET_ATON(?) GROUP BY ip ORDER BY ip ASC";
    $options = '';
    foreach (db_all($sql_pipcheck, [$ip]) as $r_pipcheck) {
        
        $playerip = $r_pipcheck['ip'];
        $trueplayerip = long2ip($playerip);
        $pidcount = $r_pipcheck['pidcount'] ?? null;
        
        // If there is more than one pid associated to an IP ...
        IF ($pidcount > 1 ) {
            echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm">';
            echo "<br>$trueplayerip";
            
            // Query for player names and ids associated to that ip during the cycle
            $sql_pcheck = "SELECT pi.id, pi.name, pi.country, p.pid, p.ip FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
            
            foreach (db_all($sql_pcheck) as $r_pcheck) {
                echo '<br><a class="darkhuman" href="./?p=pinfo&amp;pid='.$r_pcheck['pid'].'">'.FormatPlayerName($r_pcheck['country'], $r_pcheck['pid'], $r_pcheck['name']).'</a> ';
                $options .= '<OPTION value="'.$r_pcheck['pid'].'">'.$r_pcheck['name'].'</OPTION>';
            }
            echo '<br><br>Merge to: <SELECT NAME="merge_to">'.$options.'</SELECT>';
            echo "<br><INPUT TYPE=\"hidden\" NAME=\"ip\" VALUE=\"".htmlspecialchars($ip)."\"><INPUT TYPE=\"SUBMIT\" VALUE=\"Player merge\" NAME=\"submit\"></FORM>";
            echo '<br />';
        }
    }
        
// CASE: MERGE NICKS WITH SHARED IP              
} else if(($_POST['submit'] ?? '') == "Player merge") {
	echo "<P>Merging nicks with shared ip</P>";
	
    $ip = str_replace("_",".",$_POST['ip']);
    $merge_to_pid = (int)$_POST['merge_to'];
    $sql_pipcheck = "SELECT ip, COUNT(DISTINCT pid) AS pidcount FROM uts_player WHERE ip = INET_ATON(?) GROUP BY ip ORDER BY ip ASC";
    $pid_from = array();
    foreach (db_all($sql_pipcheck, [$ip]) as $r_pipcheck) {
        
        $playerip = $r_pipcheck['ip'];
        $trueplayerip = long2ip($playerip);
        $pidcount = $r_pipcheck['pidcount'] ?? null;
        
        // If there is more than one pid associated to an IP ...
        IF ($pidcount > 1 ) {
            echo "$trueplayerip<br><br>merge:<br>";
            
            // Query for player names and ids associated to that ip during the cycle
            $sql_pcheck = "SELECT p.pid FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
            
            $i=0;
            foreach (db_all($sql_pcheck) as $r_pcheck) {
                if($r_pcheck['pid'] != $merge_to_pid) {
                    $pid_from[$i] = $r_pcheck['pid'];
                    echo $pid_from[$i].'<br>';
                    $i++;
                }
            }
            echo '<br />merge to: '.$merge_to_pid;
        }
    }
    if($debug) echo "<br> -- started merging";
    for($j=0;$j<count($pid_from);$j++) {
        merge_players($merge_to_pid, $pid_from[$j]);
    }
    if($debug) echo "<br> -- merging ended";
    
// CASE: SHOW ALL IP'S LINKED TO MORE THAN ONE NICK - ONLY RANGE    
} else if(($_GET['onlyrange'] ?? '') == "true") {
	if(($_POST['submit'] ?? '') == "Confirm") {
		echo "<P>Showing all ip's in range ".htmlentities($_POST['from'])." to ".htmlentities($_POST['to'])."<br><I>If you want to ignore some ip's, because for example different but unrelated nicks are associated with it, check these and press the button at the lower end to confirm this</I></P>";
		$from = $_POST['from'];
		$to = $_POST['to'];
		
	    $ignore_ips = db_all("SELECT ip FROM uts_ignoreips WHERE ip >= INET_ATON(?) AND ip <= INET_ATON(?)", [$from, $to]);
	    $extended_query = "WHERE ip >= INET_ATON(?) AND ip <= INET_ATON(?)";
	    $extended_params = array($from, $to);
	    $i=0;
	    foreach ($ignore_ips as $ignore_ips_array) {
	        $ip = (int)$ignore_ips_array[0];
	        $extended_query .= " AND ";
	        $extended_query .= "ip <> '".$ip."'";
	        $i++;
	    }
	    
	    echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm" target="_blank">';
	    
	                
	    // Query for list of unique ips and player ids
	    $sql_pipcheck = "SELECT ip, COUNT(DISTINCT pid) AS pidcount FROM uts_player ".$extended_query." GROUP BY ip ORDER BY ip ASC";
	    $q_pipcheck = db_all($sql_pipcheck, $extended_params);
	    if(count($q_pipcheck) > 0) {
		    foreach ($q_pipcheck as $r_pipcheck) {
		        
		        $playerip = $r_pipcheck['ip'];
		        $trueplayerip = long2ip($playerip);
		        $pidcount = $r_pipcheck['pidcount'] ?? null;
		        
		        // If there is more than one pid associated to an IP ...
		        IF ($pidcount > 1 ) {
		            
		            echo "<br><input type=checkbox name=$trueplayerip> $trueplayerip";
		            
		            // Query for player names and ids associated to that ip during the cycle
		            $sql_pcheck = "SELECT pi.id, pi.name, pi.country, p.pid, p.ip FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
		            foreach (db_all($sql_pcheck) as $r_pcheck) {
		                echo '<br><a class="darkhuman" href="admin.php?key='.$adminkey.'&amp;action=pinfo&amp;pid='.$r_pcheck['pid'].'">'.FormatPlayerName($r_pcheck['country'], $r_pcheck['pid'], $r_pcheck['name']).'</a> ';
		            }	
		            echo "<br><INPUT TYPE=\"SUBMIT\" VALUE=\"merge - $trueplayerip\" NAME=\"submit\">";
		            echo '<br />';
		        }
		    }
		    
		    
		    echo '<BR><INPUT TYPE="SUBMIT" VALUE="ignore" NAME="submit"></FORM>';	
	    } else {
			echo "<P><BR>No ip's found with more than one nick linked to it and which are not ignored and are in this range</P>";	
		}
	    	
	} else {
		echo "<P>Showing all ip's in range - Enter range to filter on</P>";
    	echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm&onlyrange=true">';
    	echo '<BR>Ranging from <INPUT TYPE="TEXT" NAME="from" SIZE="20" VALUE="0.0.0.0"> to <INPUT TYPE="TEXT" NAME="to" SIZE="20" VALUE="255.255.255.255">';
		echo '<BR><BR><INPUT TYPE="SUBMIT" NAME="submit" VALUE="Confirm"></FORM>';
	}	

// CASE: SHOW ALL IP'S LINKED TO MORE THAN ONE NICK
} else {
	echo "<P>IP's linked with more than one nick<br><I>If you want to ignore some ip's, because for example different but unrelated nicks are associated with it, check these and press the button at the lower end to confirm this</I></P>";
	
    $ignore_ips = db_all("SELECT ip FROM uts_ignoreips");
    $extended_query = "";
    $i=0;
    foreach ($ignore_ips as $ignore_ips_array) {
        $ip = (int)$ignore_ips_array[0];
        if($i==0)
            $extended_query = " WHERE ";
        else
            $extended_query .= " AND ";
        $extended_query .= "ip <> '".$ip."'";
        $i++;
    }
    
    echo '<FORM METHOD="POST" ACTION="admin.php?key='.$adminkey.'&action=plm" target="_blank">';
    
                
    // Query for list of unique ips and player ids
    $sql_pipcheck = "SELECT ip, COUNT(DISTINCT pid) AS pidcount FROM uts_player ".$extended_query." GROUP BY ip ORDER BY ip ASC";
    $q_pipcheck = db_all($sql_pipcheck);
    if(count($q_pipcheck) > 0) {
	    foreach ($q_pipcheck as $r_pipcheck) {
	        
	        $playerip = $r_pipcheck['ip'];
	        $trueplayerip = long2ip($playerip);
	        $pidcount = $r_pipcheck['pidcount'] ?? null;
	        
	        // If there is more than one pid associated to an IP ...
	        IF ($pidcount > 1 ) {
	            
	            echo "<br><input type=checkbox name=$trueplayerip> $trueplayerip";
	            
	            // Query for player names and ids associated to that ip during the cycle
	            $sql_pcheck = "SELECT pi.id, pi.name, pi.country, p.pid, p.ip FROM uts_pinfo AS pi, uts_player AS p WHERE pi.id = p.pid AND p.ip = $playerip GROUP BY pi.id, pi.name, p.pid, p.ip, pi.country";
	            foreach (db_all($sql_pcheck) as $r_pcheck) {
	                echo '<br><a class="darkhuman" href="admin.php?key='.$adminkey.'&amp;action=pinfo&amp;pid='.$r_pcheck['pid'].'">'.FormatPlayerName($r_pcheck['country'], $r_pcheck['pid'], $r_pcheck['name']).'</a> ';
	            }	
	            echo "<br><INPUT TYPE=\"SUBMIT\" VALUE=\"merge - $trueplayerip\" NAME=\"submit\">";
	            echo '<br />';
	        }
	    }
    	echo '<BR><INPUT TYPE="SUBMIT" VALUE="ignore" NAME="submit"></FORM>';
    } else {
    	echo "<P><BR>No ip's found with more than one nick linked to it and which are not ignored</P>";	
    }
}

// If debugmode is on, determine end time & output execution time
if($debug) {
	$time = microtime();
	$time = explode(" ", $time);
	$time = $time[1] + $time[0];
	$endtime = $time;
	$totaltime = ($endtime - $begintime);
	echo "<br>execution time: $totaltime";
}
?>