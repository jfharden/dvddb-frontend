<?php print "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>\n";

    require_once("/secrets/config.php");
    require_once("quickdb.php");

    $dbh = connectToDatabase(
        $_CONF['db_name'], 
        $_CONF['db_host'], 
        $_CONF['db_user'], 
        $_CONF['db_pass'],
        $_CONF['sslmode'],
        $_CONF['sslrootcert']
    );

    $genres;
    $years;
    $ratings;
    $types;
    $containers;
    $advancedSearch = false;

    initLists();

    $searchedYears = getSearchListParams("year","//");
    $searchedRatings = getSearchListParams("rating");
    $searchedGenres = getSearchListParams("genre");
    $searchedTypes = getSearchListParams("types");
    $searchedText = getSearchListParams("title", "//");

    $advancedDisplay = "display: none;";

    if ($searchedYears != false || $searchedRatings != false || $searchedGenres != false || $searchedTypes != false)
    {
        $advancedDisplay = "";
    }

    if ($searchedYears == false && $searchedRatings == false && $searchedGenres == false && $searchedTypes == false && $searchedText == false)
    {
        $query = "SELECT 0=1";
    }
    else
    {
        $query = buildSearchQuery($searchedText, $searchedYears, $searchedRatings, $searchedGenres, $searchedTypes);
    }

    $tableData = buildTableData($query);
?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>
        DVDDB
    </title>
    <style type='text/css'>
        .rate, .rate b {
            width: 200px; // = total width of the stars (10 * 20px)
            height: 20px;
            overflow: hidden;
            background: url(images/diamond.gif);
        }
        .rate b {
            float: left;
            background-color: #FDCE30;
            height: 20px;
        }    
    </style>
    <link href="style.css" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="js/jquery.js"></script> 
    <script type="text/javascript" src="js/jquery.tablesorter.custom.js"></script> 
    <script type="text/javascript">
        <!--
        <?php
            foreach ($tableData as $imdb_id => $entity)
            {
                print "var metaInfo$entity->meta_data_id = \"" . $entity->infoRowString() . "\";\n";
            }
        ?>

        // This is called from inside a custom patched verison of tablesorter
        function clearMetaInfoRows()
        {
                $('.metaInfoRow').each(function() {
                    $(this).remove();
                });

                return true;
        }


        function preload(arrayOfImages) {
            $(arrayOfImages).each(function(){
                $('<img/>')[0].src = this;
            });
        }

        $(document).ready(function() { 
            $("#discsTable").tablesorter(); 

            $("#openButton").click(function() {
                if ($('#searchLists').is(':visible') )
                {
                    $('#searchLists').slideUp();
                    $('#openButton').text("Show Advanced Options");
                }
                else
                {
                    $('#searchLists').slideDown();
                    $('#openButton').text("Hide Advanced Options");
                }
            });

            <?php
                $urls = array();
                foreach ($tableData as $imdb_id => $entity)
                {
                    $urls[] = "'https://jfharden-public.s3-eu-west-1.amazonaws.com/dvddb/posters/$imdb_id.jpg'";

                    print "\n";
                    print "$('#groupRow_$entity->meta_data_id').click(function() { \n";
                    print "    if (!removeRow('#metaInfo_$entity->meta_data_id')) \n";
                    print "    { \n";
                    print "        $('#groupRow_$entity->meta_data_id').after(metaInfo$entity->meta_data_id); \n";
                    print "    } \n";
                    print "});\n";
                }

                print "preload([" . implode(",", $urls) . "]);";
            ?>
        });  

        function removeRow(name)
        {
            if ($(name).length > 0)
            {
                $(name).remove();
                return true;
            }
            return false;
        }
        // -->
    </script> 
</head>
<body>
<div>
    <h1>DVD DB</h1>
</div>
<!-- SEARCH BOX -->
<div class="choice">
    <h2>Search</h2>
    <form action='index.php' method='post'>
        <div style='width: 100%; text-align: center'>
            <div>
                <input name='title' style='margin: auto; width: 75%; border: solid 1px black' value='<?=stripslashes(htmlspecialchars($_POST['title'], ENT_QUOTES))?>' />
                <input type='submit' value='Search' />
            </div>
            <div style='clear: both; margin-top: 0.5em; width: 100%; <?=$advancedDisplay?>' id='searchLists' >
                <?php printSearchList("year", "Year", $years, $searchedYears, false) ?>
                <?php printSearchList("rating", "Certification", $ratings, $searchedRatings); ?>
                <?php printSearchList("genre", "Genre", $genres, $searchedGenres); ?>
                <?php printSearchList("types", "Type", $types, $searchedTypes); ?>
                <div style='clear: both'> </div>
            </div>
            <div style='clear:both; margin-top: 0.5em; width: 100%; text-align: center'>
                <a href='#' style='color: black; font-size: 0.7em' id="openButton">Show Advanced Options</a>
            </div>
        </div>
    </form>
</div>
<!-- DISC LIST -->
<div class="choice" style='clear: both'>
    <h2>Discs (<?=count($tableData)?>)</h2>
    <?php
    if (count($tableData) > 0)
    {
        usort($tableData, "sortGroupsByName");
        ?>
        <table id="discsTable" class='imdbdata'>
        <thead>
        <tr id='imdbheader'>
            <th>Title</th>
            <th>Year</th>
            <th>Rating</th>
            <th>Cert</th>
            <th>Genres</th>
            <th>Type</th>
            <th>Format</th>
        </tr>
        </thead>
        <tbody>

        <?php
        foreach ($tableData as $imdb_id => $entity)
        {
            print $entity;
        }
        ?>
        
        </tbody>
        </table>
    <?php
    } // END numRows() > 0
    ?>
</div>
<div class="footer">
    <?php
        function getServerPort() {
            if (array_key_exists('HTTP_X_FORWARDED_PORT', $_SERVER)) {
                return $_SERVER["HTTP_X_FORWARDED_PORT"];
            } else if (array_key_exists('SERVER_PORT', $_SERVER)) {
                return $_SERVER["SERVER_PORT"];
            } else {
                return "80";
            }   
        }

				function getLogoutUrl() {
            $host = $_SERVER["HTTP_HOST"];
    
            $scheme = getServerPort() == "443" ? "https" : "http";
    
            return urlencode("$scheme://$host/loggedout.php");
				}
    ?>
    &copy; Copyright Jonathan Harden 2016 -
    <a href='/redirect_uri?logout=<?=getLogoutUrl()?>'>Logout</a>
</div>
</body>
</html>
<?php
/*********************************************
 *
 * Helper functions
 *
 *********************************************/

function initLists()
{
    global $genres, $types, $ratings, $years, $containers;

    $genres = getAllRows("SELECT * FROM genres ORDER BY genre", "genre", "id");
    $years = getAllRows("SELECT DISTINCT year FROM meta_data ORDER BY YEAR DESC" , "year");
    $types = getAllRows("SELECT * FROM types ORDER BY type", "type", "id");
    $ratings = getAllRows("SELECT * FROM age_ratings ORDER BY rating", "rating", "id");
    $containers = getAllRows("SELECT * FROM containers", "name", "id");
}

function printMultiselect($name, $list, $selectedList, $useKeys = true, $rows = 7)
{
    if ($selectedList === false)
    {
        $selectedList = array();
    }
    print "<select name='" . $name . "[]' size='7' style='width: 8em' multiple='multiple'>\n";
    foreach ($list as $k => $v)
    {
        if ($useKeys)
        {
            $optVal = $k;
        }
        else
        {
            $optVal = $v;
        }
        if (in_array($optVal, $selectedList))
        {
            $selected = "selected='selected'";
        }
        else
        {
            $selected = "";
        }
        print "    <option $selected value='$optVal'>$v</option>";
    }
    print "</select>\n";
}

function printError($message, $dontEncode = false)
{
    print "<div class='error'>" . htmlspecialchars($message) . "</div>";
}

function printSuccess($message, $dontEncode = false)
{
    if ($dontEncode)
    {
        print "<div class='success'>\n" . $message . "\n</div>\n";
    }
    else
    {
        print "<div class='success'>" . htmlspecialchars($message) . "</div>";
    }
}

function printSearchList($name, $alias, $list, $selected, $useKeys = true)
{
    print "<div style='float: left; width: 25%; font-weight: bold; font-size: small'>\n";
    print "    $alias\n";
    print " <br />\n";
    printMultiselect($name, $list, $selected, $useKeys);
    print "</div>";
}

function getSearchListParams($name, $validation = '/^\d+$/')
{
    $postField = $_POST[$name];
    $results = array();

    if (isset($postField))
    {
        if (is_array($postField))
        {
            foreach ($postField as $value)
            {
                if (!preg_match($validation, $value))
                {
                    return false;
                }
            }
            return $postField;
        }
        else
        {
            if (!preg_match($validation, $value))
            {
                return false;
            }
            return $postField;
        }
    }
    return false;
}

function buildSearchQuery($title, $years, $ratings, $genres, $types)
{

    $where = "";
    $where .= generateMysqlInClause("md.year", $years, $quote=true);

    $ratingsSearch .= generateMysqlInClause("md.age_rating_id", $ratings);
    if ($ratingsSearch != "" && $where != "") $where .= " AND ";
    $where .= $ratingsSearch;
    
    $genresSearch .= generateMysqlInClause("g.id", $genres);
    if ($genresSearch != "" && $where != "") $where .= " AND ";
    $where .= $genresSearch;
    
    $typeSearch .= generateMysqlInClause("md.type_id", $types);
    if ($typeSearch != "" && $where != "") $where .= " AND ";
    $where .= $typeSearch;

    if (isset($title) && $title != "")
    {
        // Meta data title search
        $textSearch = buildTextSearch($title);
        if ($where != "") $where .= " AND ";
        $where .= "((" . $textSearch;

        // Disc LIKE title search
        $escaped = pg_escape_literal("%" . $title . "%");
        $discsNameSearch = ") OR (d.name LIKE {$escaped}))";
        $where .= $discsNameSearch;
    }
    
    if ($where != "")
    {
        $where = "WHERE " . $where . " AND d.meta_data_id IS NOT NULL";
    }
    else
    {
        $where = "WHERE d.meta_data_id IS NOT NULL";
    }

    $query = "
        SELECT     d.id AS disc_id,
                d.meta_data_id AS mdid,
                aka.id AS aka_id,
                *
        FROM discs AS d
        LEFT JOIN textSearchVectors AS tsv
            ON d.id = tsv.disc_id
        LEFT JOIN meta_data AS md
            ON d.meta_data_id = md.id
        LEFT JOIN meta_genres AS mg
            ON md.id = mg.meta_data_id
        LEFT JOIN genres AS g
            ON mg.genre_id = g.id
        LEFT JOIN meta_actors AS ma
            ON md.id = ma.meta_data_id
        LEFT JOIN actors AS a
            ON ma.actor_id = a.id
        LEFT JOIN also_known_as AS aka
            ON md.id = aka.meta_data_id
        $where
    ";

    $query .= " " . getOrder();

    return $query;
}

function buildTextSearch($text)
{
    $textSearch = "";
    $text = stripslashes(rawurldecode($text));

    $textSearch = " setweight(tsv.nameVec, 'A') || ' ' ||
           setweight(tsv.titleVec, 'A') || ' ' ||
           setweight(tsv.akaVec, 'A') || ' ' ||
           setweight(tsv.actorVec, 'B') || ' ' ||
           setweight(tsv.plotVec, 'C') || ' '
           @@ plainto_tsquery('english', '" . pg_escape_string($text) . "') ";

    return $textSearch;
}

function generateMysqlInClause($column, $list, $quote=false)
{
    $result = "";

    if (is_array($list) && count($list) > 0)
    {
        $result .= $column . " IN (";
        foreach ($list as $item)
        {
            if ($quote) $result .= "'";
            $result .= pg_escape_string($item);
            if ($quote) $result .= "'";
            $result .= ",";
        }
        $result = rtrim($result, ",");
        $result .= ")";
    }

    return $result;
}

function getOrder()
{
    return "ORDER BY d.season,d.disc_number";
}

function buildTableData($query)
{
    global $genres, $years, $ratings, $types, $containers;

    $tableData = array();

    startMultiRowLookup($query);

    while ($row = getNextRowAssoc())
    {
        // Ignore all which don't have IMDB data, sucks to be them!
        if (isset($row['imdb_id']) && $row['imdb_id'] != "")
        {
            if (!array_key_exists($row['imdb_id'], $tableData))
            {
                $group = new Grouping($genres, $years, $ratings, $types, $containers);
                $tableData[$row['imdb_id']] = $group;
            }

            $tableData[$row['imdb_id']]->addData($row);
        }
    }

    return $tableData;
}

function sortGroupsByName($a, $b)
{
    if ($a->imdb_title == $b->imdb_title) return 0;
    if ($a->imdb_title <= $b->imdb_title) return -1;
    if ($a->imdb_title >= $b->imdb_title) return 1;
}

function clean($str)
{
    return stripslashes(htmlspecialchars($str, ENT_QUOTES));
}

class Grouping
{
    var $imdb_title;
    var $type;
    var $year;
    var $rating;
    var $rated;
    var $imdb_poster;
    var $imdb_url;
    var $imdb_id;
    var $rating_count;
    var $plot;
    var $meta_data_id;
    var $discs = array();
    var $genres = array();
    var $actors = array();
    var $containers = array();
    var $also_known_as = array();
    var $meta_data_ingested = false;

    var $genreList;
    var $yearList;
    var $ratingList;
    var $typeList;

    function __construct($genres, $years, $ratings, $types, $containers)
    {
        $this->genreList = $genres;
        $this->yearList = $years;
        $this->ratingList = $ratings;
        $this->typeList = $types;
        $this->containers = $containers;
    }

    function ingestMetaData($data)
    {
        $this->imdb_title = $data['title'];
        $this->type = $this->typeList[$data['type_id']];
        $this->year = $data['year'];
        $this->rating = $data['rating'];
        $this->rated = $this->ratingList[$data['age_rating_id']];
        $this->imdb_poster = $data['poster'];
        $this->imdb_url = $data['imdb_url'];
        $this->imdb_id = $data['imdb_id'];
        $this->rating_count = $data['rating_count'];
        $this->plot = $data['plot'];
        $this->meta_data_id = $data['mdid'];

        $this->meta_data_ingested = true;
    }

    function addData($data)
    {
        if (!$this->meta_data_ingested)
        {
            $this->ingestMetaData($data);
        }
        $this->addAka($data['aka']);
        $this->addGenre($data['genre']);
        $this->addActor($data['actor']);
        $this->addDisc($data);
    }

    function addAka($aka)
    {
        $this->addToArray($this->also_known_as, $aka);
    }

    function addGenre($genre)
    {
        $this->addToArray($this->genres, $genre);
    }

    function addActor($actor)
    {
        $this->addToArray($this->actors, $actors);
    }

    function addDisc($data)
    {
        if (!array_key_exists($data['disc_id'], $this->discs))
        {
            $this->discs[$data['disc_id']] = $data;
        }
    }

    function addToArray(&$array, $item)
    {
        if (!in_array($item, $array))
        {
            $array[] = $item;
        }
    }

    function __toString()
    {
        $string  = "\n";
        $string .= "<tr id='groupRow_".$this->meta_data_id."'>\n";
        $string .= "    <td class='titleColumn' title='" . htmlspecialchars($this->arrayToString($this->also_known_as, "\n"), ENT_QUOTES) . "'><a href='$this->imdb_url'>$this->imdb_title</a></td>\n";
        $string .= "    <td>$this->year</td>\n";
        $string .= "    <td>$this->rating</td>\n";
        $string .= "    <td>$this->rated</td>\n";
        $string .= "    <td>" . $this->arrayToString($this->genres) . "</td>\n";
        $string .= "    <td>$this->type</td>\n";
        $string .= "    <td>" . ($this->blueray ? "Blu-ray" : "DVD") . "</td>\n";
        $string .= "</tr>\n";
        return $string;
    }

    function infoRowString()
    {
        $string  = "<tr id='metaInfo_$this->meta_data_id' style='padding: 0.4em' class='metaInfoRow'>";
            $string .= "<td colspan='7' style='text-align: left;'>";
            $string .= "<div>";
                $string .= "<img alt='" . clean($this->imdb_title) . " poster' title='" . clean($this->imdb_title) . " Poster' src='https://jfharden-public.s3-eu-west-1.amazonaws.com/dvddb/posters/$this->imdb_id.jpg' style='float: left; border: 0; height: 50%; padding: 0; margin: 0.3em; border: solid black 1px;' />";
                $string .= "<h3 style='font-size: 1.2em; margin: 0'>" . clean($this->imdb_title) . "</h3>";
                $string .= "<p style='margin: 0; font-size: 0.7em; color: #EAEAEA'>";

                foreach ($this->also_known_as as $aka)
                {
                    $string .= "(" . clean($aka) . ")<br />";
                }
                // $string .= substr($string, 0, -6);

                $string .= "</p>";
                $string .= "<div class='rate' style='padding:0; margin:0'>";
                    $string .= "<b style='width: " . ($this->rating * 10) . "%; padding:0; margin:0;'>&nbsp;</b>";
                $string .= "</div>";
                $string .= clean($this->plot);
            $string .= "</div>";
            $string .= "<table style='width: 100%; border-collapse: collapse; margin-left: auto; margin-right: auto; clear: both;'>";
            $string .= "<thead>";
            $string .= "<tr>";
                $string .= "<th>Container</th>";
                $string .= "<th>Index</th>";
                $string .= "<th>Disc name</th>";
                $string .= "<th>Season</th>";
                $string .= "<th>Disc number</th>";
                $string .= "<th>Total discs</th>";
            $string .= "</tr>";
            $string .= "</thead>";
            $string .= "<tbody>";
            foreach ($this->discs as $disc)
            {
                $string .= "<tr>";
                    $string .= "<td style='background-color: #c0c0c0'>" . clean(rawurldecode($this->containers[$disc['in_container']])) . "</td>";
                    $string .= "<td style='background-color: #c0c0c0'>" . $disc['index'] . "</td>";
                    $string .= "<td style='background-color: #c0c0c0'>" . clean($disc['name']) . "</td>";
                    $string .= "<td style='background-color: #c0c0c0'>" . $disc['season'] . "</td>";
                    $string .= "<td style='background-color: #c0c0c0'>" . $disc['disc_number'] . "</td>";
                    $string .= "<td style='background-color: #c0c0c0'>" . $disc['total_discs'] . "</td>";
                $string .= "</tr>";
            }
            $string .= "</tbody>";
            $string .= "</table>";
            $string .= "</td>";
        $string .= "</tr>";

        return $string;
    }

    function arrayToString(&$array, $separator = ", ")
    {
        return implode($separator, $array);
    }
}

?>

