<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include_once 'includes/renderer-general-output.php';

global $renderer_heigth;
global $renderer_width;

$prevCategory = "";
$chartOutput = "";
$prevRenderedChart = "";

// get all charts for this match
$charts = db_query("SELECT d.* , t.charttype, t.category, t.type, t.color, t.layout,t.columns
	FROM uts_chartdata d
	JOIN uts_charttypes t ON d.chartid = t.id
	WHERE d.mid = ?
	ORDER BY d.id ASC", [$mid]);

$chartCount = $charts->rowCount();

if ($chartCount > 0) {
    $i = 0;

    // cycle over charts
    while ($chart = $charts->fetch()) {

        // retrieve both generic as the specific data
        $category = $chart['category'];
        $type = $chart['type'];
        $color = $chart['color'];
        $layout = $chart['layout'];
        $charttype = $chart['charttype'];
        $columns = $chart['columns'];
        $title = $chart['title'];
        $data = unserialize(gzdecode((string) $chart['data']), ['allowed_classes' => false]);
        $labels = unserialize(gzdecode((string) $chart['labels']), ['allowed_classes' => false]);
        $categories = unserialize(gzdecode((string) $chart['categories']), ['allowed_classes' => false]);

        // append previous chart - this is done to ensure proper outlining (can only know in +1 round)
        $chartOutput .= $prevRenderedChart;

        // print a new section if we're now in a different category
        if ($category != $prevCategory) {

            if (strlen($prevCategory) > 0) {
                $chartOutput .= renderFootBlock();
            }

            $chartOutput .= renderHeaderBlock($category);
            $prevCategory = $category;

        } else {
            if ($i > 1 && $i % 2 == 0) {
                $chartOutput .= "</td></tr><tr><td>";
            } else {
                $chartOutput .= "</td><td>";
            }
        }

        $prevRenderedChart = renderChart($mid."-".$i, $layout, $color, $title, $data, $labels, $categories, $renderer_width * $columns, $renderer_heigth, $charttype);

        $i++;
    }

    // finishing up
    $chartOutput .= $prevRenderedChart;
    $chartOutput .= renderFootBlock();

    echo '
	<script type="text/javascript">
		function toggle_visibility(id) {
		  var e = document.getElementById(id);
		  if (e.style.display != "none")
			  e.style.display = "none";
		  else
			  e.style.display = "";
		}
	</script>';

    echo $chartOutput;
}
