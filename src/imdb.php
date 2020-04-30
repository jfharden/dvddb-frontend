<?php print "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>\n"; ?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <link href="style.css" rel="stylesheet" type="text/css" />
    <title>
        Evil Indexer IMDB Data Importer
    </title>
</head>
<body>
<div>
<?php
    require_once("/secrets/config.php");
    require_once("quickdb.php");

    $imdbIdProvided = false;

    $dbh = connectToDatabase(
        $_CONF['db_name'],
        $_CONF['db_host'],
        $_CONF['db_user'],
        $_CONF['db_pass'],
        $_CONF['sslmode'],
        $_CONF['sslrootcert']
    );

    if (isset($_POST['reset_skip']) && strcmp($_POST['reset_skip'],"RESET_SKIP") == 0)
    {
        if (resetSkip())
        {
            $success = "Skip tag reset for all discs.";
        }
        else
        {
            $error = "Failed to reset skip tag for all discs.";
        }
    }

    if (isset($_POST['skip']))
    {
        if (strcmp($_POST['skip'],"SKIP") == 0)
        {
            if (markDiscToIgnore($_POST['disc']))
            {
                $success = "<form action='imdb.php' method='post'>\n";
                $success .= "Set disc " . $_POST['disc'] . " to be skipped";
                $success .= "    <input type='hidden' name='disc' id='disc' value='" . $_POST['disc'] . "' />\n";
                $success .= "    <input type='hidden' name='skip' id='skip' value='UNIGNORE' />\n";
                $success .= "    <input type='submit' value='Undo' />\n";
                $success .= "</form>";
            }
            else
            {
                $error = "Failed to mark disc " . $_POST['disc'] . " to skip";
            }
        }
        elseif (strcmp($_POST['skip'],"UNIGNORE") == 0)
        {
            if (unignoreDisc($_POST['disc']))
            {
                $success = "Set disc " . $_POST['disc'] . " to not be skipped";
            }
            else
            {
                $error = "Failed to stop skipping disc " . $_POST['disc'] . " to skip";
            }
        }
    }
    elseif (isset($_POST['commit']) && strcmp($_POST['commit'],"COMMIT") == 0)
    {
        if (isset($_POST['chooseExisting']) && strcmp($_POST['chooseExisting'], "") != 0)
        {
            $disc_id = $_POST['disc'];

            $disc_results = updateDisc($disc_id, $_POST['chooseExisting']);
            if ($disc_results)
            {

                $success = "Updated disk " . $disc_id . " to reference entry " . $_POST['chooseExisting'];

                if (!generateSearchVectors($disc_id)) {
                    $error = "Couldn't generate the search vectors for $data->Title with disc_id $disc_id";
                }
            }
        }
        else
        {
            $data = json_decode(rawurldecode($_POST['data']));
        
            if ($data === NULL)
            {
                $error = "<p class='error'>Commit specified but no data found</p>";
            }
            else
            {
                function stringToArray($string) {
                    if (!is_array($string)) {
                        $compactString = str_replace(' ', '', $string);
                        return split(",", $compactString);
                    }
                    else {
                        return $string;
                    }
                }

                $genres = array();
                foreach (stringToArray($data->Genre) as $genre)
                {
                    $genres[] = insertGenre($genre);
                }

                $actors = array();
                foreach (stringToArray($data->Actors) as $actor)
                {
                    $actors[] = insertActor($actor);
                }

                if (isset($data->Type))
                {
                    $data->type_id = insertType($data->Type);
                }

                if (isset($data->Rated))
                {
                    $data->rated_id = insertAgeRating($data->Rated);
                }

                $data->imdb_url = "http://www.imdb.com/title/" . $data->imdbID . "/";

                $entry = insertEntry($data);
                if (!$entry)
                {
                    $error = "Couldn't insert an entry for $data->Title.";
                }

                /*
                if (is_array($data->also_known_as) && count($data->also_known_as) > 0)
                {
                    $aka_result = insertAKA($entry, $data->also_known_as);
                }
                */

                $key_results = addManyToManyKeys($entry, $genres, $actors);
                $disc_id = $_POST['disc'];
                $disc_results = updateDisc($disc_id, $entry);

                if (!generateSearchVectors($disc_id)) {
                    $error = "Couldn't generate the search vectors for $data->Title with disc_id $disc_id";
                }

                if ($key_results && $disc_results)// && $aka_result)
                {
                    $success = "Added meta data for $data->Title";
                }

                getPoster($data->imdbID, $data->Poster);
            }
        }
    }


    $options = getTitleOptions();

    $query = "SELECT DISTINCT(discs.name),discs.id,index,containers.name AS case FROM discs LEFT OUTER JOIN containers ON discs.in_container = containers.id WHERE meta_data_id IS NULL AND skip_meta_data = FALSE ORDER BY name, id LIMIT 1";
    if (isset($_POST['modifySearch']) && strcmp($_POST['modifySearch'], "") != 0)
    {
        if (is_numeric($_POST['disc']))
        {
            $query = "SELECT name, id FROM discs WHERE id=" . $_POST['disc'];
        }
        else
        {
            die("NORTY!");
        }
    }

    startMultiRowLookup($query);

    function stripPunctuation($string)
    {
        return preg_replace('/[^\w -]+/', '', $string);
    }

    $disc_id = -1;

    if (numRows() > 0)
    {
        $row = getNextRowAssoc();

        $disc_id = $row['id'];
        print "<h1>'" . rawurldecode($row['name']) . "' in " . rawurldecode($row['case']) . " position " . $row['index'] . "</h1>\n";

        if (isset($_POST['SELECT_FROM_IMDB_SEARCH']) && preg_match('/^tt\d+$/', $_POST['SELECT_FROM_IMDB_SEARCH']))
        {
            $imdbIdProvided = $_POST['SELECT_FROM_IMDB_SEARCH'];
        }

        if (isset($success))
        {
            printSuccess($success,true);
        }
        
        if (isset($error))
        {
            printError($error);
        }

        function searchWithOMDB($imdbId) {
            // The search type for search by title
            $jsonRequest = array(
                    "i" => $imdbId,
                    "r" => "json",
                    "plot" => "full",
                    "v" => "1"
            );

            return makeOMDBJSONRequest($jsonRequest);
        }

        function searchWithIMDB($row) {
            $jsonSearch = stripPunctuation($row['name']);

            if (isset($_POST['modifySearch']) && $_POST['modifySearch'] == "SEARCH")
            {
                $jsonSearch = stripPunctuation($_POST['title']);
            }

            $jsonRequest = array(
                "q" => $jsonSearch,
                "json" => 1,
                "nr" => "1",
                "tt" => "on"
            );

            return makeIMDBJSONRequest($jsonRequest);
        }

        if ($imdbIdProvided)
        {
            $data = searchWithOMDB($imdbIdProvided);
        }
        else
        {
            $data = searchWithIMDB($row);
        }

        if (!$data)
        {
            die("ERROR: Couldn't do the JSON request");
        }

        ?>
        <div class="choice">
            <form action='imdb.php' method='post'>
                <h2>Modify the IMDB search</h2>
                <?php
                    if (isset($_POST['title']) && strcmp($_POST['title'], "") != 0)
                    {
                        $searchTerm = stripslashes(htmlspecialchars($_POST['title']));
                    }
                    else
                    {
                        $searchTerm = htmlspecialchars($row['name']);
                    }
                ?>
                <input type='hidden' name='modifySearch' id='modifySearch' value='SEARCH' />
                Title: <input name='title' id='title' value="<?php print rawurldecode($searchTerm); ?>" /><br />
                Year: <input name='year' id='year' value='<?=$_POST['year']?>' style='width: 3em' /><br />
                or<br />
                IMDB ID: <input name='SELECT_FROM_IMDB_SEARCH' id='SELECT_FROM_IMDB_SEARCH' value='<?=$_POST['SELECT_FROM_IMDB_SEARCH']?>' style='width: 7em' />
                <input type='hidden' name='disc' id='disc' value='<?=$row['id']?>' />
                <input type='submit' value='Modify search' />
            </form>
        </div>
        <div class="or">or</div>
        <div class="choice">
            <form action='imdb.php' method='post'>
                <h2>Choose an existing entry: </h2>
                <input type='hidden' name='commit' id='commit' value='COMMIT' />
                <input type='hidden' name='disc' id='disc' value='<?=$row['id']?>' />

                <select name='chooseExisting' id='chooseExisting'>
                    <option value=''>-- IMDB Option --</option>
                    <?php
                        foreach ($options as $id => $info)
                        {
                            $selected = "";
                            if ($info['title'] == $row['name'])
                            {
                                $selected = "selected='selected'";
                            }

                            print "\t\t\t\t<option $selected value='$id'>" . $info['title'] . " [" . $info['year'] . "]</option>\n";
                        }
                    ?>
                </select>
                <input type='submit' value='Choose' />
            </form>
        </div>
        <div class="or">or</div>
        <div class="choice">
            <h2>Associate with an IMDB entry</h2>
            <?php
                function displayOMDBSearchResults($data, $row, $imdbId) {
                    ?>
                    <table class='imdbdata'>
                    <tr>
                        <th></th>
                        <th>Poster</th>
                        <th>Title</th>
                        <th>Year</th>
                        <th>Synopsis</th>
                        <th>Actors</th>
                    </tr>

                    <?php
                    $imdb_entry = $data;
                    ?>
                    <form action='imdb.php' method='post'>
                    <input type='hidden' name='data' id='data' value='<?php print rawurlencode(json_encode($imdb_entry)); ?>' />
                    <input type='hidden' name='commit' id='commit' value='COMMIT' />
                    <input type='hidden' name='commit_imdb_id' id='commit_imdb_id' value='<?=$imdbId;?>' />
                    <input type='hidden' name='disc' id='disc' value='<?=$row['id']?>' />
                    <tr>
                        <td><input type='submit' name='Choose' value='Choose' /></td>
                        <td><img class='poster' src='<?=$imdb_entry->Poster;?>' style='border: 0' /></td>
                        <td><?=$imdb_entry->Title;?></td>
                        <td><?=$imdb_entry->Year;?></td>
                        <td><?=$imdb_entry->Plot;?></td>
                        <td><?=$imdb_entry->Actors?></td>
                    </tr>
                    </form>
                    </table>
                    <?php
                }

                function transformIMDBResults($data) {
                    $result = array();
                    if ($data->title_exact != null) {
                        $result = array_merge($result, $data->title_exact);
                    }
                    if ($data->title_popular != null) {
                        $result = array_merge($result, $data->title_popular);
                    }
                    if ($data->title_approx != null) {
                        $result = array_merge($result, $data->title_approx);
                    }
                    if ($data->title_substring != null) {
                        $result = array_merge($result, $data->title_substring);
                    }
                    return $result;
                }

                function displayIMDBSearchResults($data, $disc_id) {
                    ?>
                    <table class='imdbdata'>
                    <tr>
                        <th></th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>IMDB ID</th>
                    </tr>

                    <?php
                    foreach (transformIMDBResults($data) as $imdb_entry)
                    {
                        ?>
                        <form action='imdb.php' method='post'>
                        <input type='hidden' name='SELECT_FROM_IMDB_SEARCH' id='SELECT_FROM_IMDB_SEARCH' value='<?=$imdb_entry->id;?>' />
                        <input type='hidden' name='disc' id='disc' value='<?=$disc_id;?>' />
                        <tr>
                            <td><input type='submit' name='Choose' value='Choose' /></td>
                            <td><?=$imdb_entry->title;?></td>
                            <td><?=$imdb_entry->description;?></td>
                            <td><a href='http://www.imdb.com/title/<?=$imdb_entry->id;?>/' target="_blank"><?=$imdb_entry->id;?></a></td>
                        </tr>
                        </form>
                        <?php
                    }
                    ?>
                    </table>
                    <?php
                }

                if ($imdbIdProvided) {
                    displayOMDBSearchResults($data, $row, $imdbIdProvided);
                }
                else {
                    displayIMDBSearchResults($data, $row['id']);
                }
            ?>
        </div>
        <div class="or">or</div>
        <div class="choice" style='text-align: center;'>
            <form action='imdb.php' method='post'>
            <h2>Skip importing data for this disc</h2>
                <input type='hidden' name='disc' id='disc' value='<?=$disc_id?>' />
                <input type='hidden' name='skip' id='skip' value='SKIP' />
                <input type='submit' value='Skip' />
            </form>    
        </div>
        <?php
    }
    else
    {
        ?>
        <h1>No discs remain</h1>
        <?php printError("There are no discs which haven't had data imported or been marked as skipped."); ?>
        <div class="choice">
            <?php
            $order = "ORDER BY ";
            if (isset($_GET['order']) && $_GET['order'] == "index")
            {
                $order .= "index";
            }
            elseif (isset($_GET['order']) && $_GET['order'] == "case")
            {
                $order .= "containers.name";
            }
            elseif (isset($_GET['order']) && $_GET['order'] == "disc")
            {
                $order .= "name";
            }
            else
            {
                $order .= "name";
            }

            $sortDirectionDisc = "";
            $sortDirectionIndex = "";

            if (isset($_GET['order']))
            {
                $orderBy = $_GET['order'];
                if ($orderBy == "disc" && !isset($_GET['direction']))
                {
                    $sortDirectionDisc = "&direction=reverse";
                }
                elseif ($orderBy == "case" && !isset($_GET['direction']))
                {
                    $sortDirectionCase = "&direction=reverse";
                }
                elseif ($orderBy == "index" && !isset($_GET['direction']))
                {
                    $sortDirectionIndex = "&direction=reverse";
                }
            }
            else
            {
                $sortDirectionDisc = "&direction=reverse";
            }

            $link_direction = "";
            if (isset($_GET['direction']) && $_GET['direction'] == "reverse")
            {
                $order .= " DESC";
                $linkDir ;
            }

            $query = "SELECT discs.name,discs.index,containers.name AS case FROM discs LEFT JOIN containers ON discs.in_container = containers.id WHERE meta_data_id IS NULL AND skip_meta_data = TRUE $order";
            startMultiRowLookup($query);

            $number = numRows();

            if ($number > 0)
            {
            ?>
            <h2><?=$number?> Skipped discs</h2>
            <form action="imdb.php" method="post">
                Removal all skip tags? 
                <input type="hidden" name="reset_skip" value="RESET_SKIP" />
                <input type="submit" value="Reset" />
            </form>
            <table class='imdbdata' style='width: auto; margin: 0.5em 0.2em'>
            <tr>
                <th><a href='imdb.php?order=disc<?=$sortDirectionDisc?>' style='color: white;'>Disc</a></th>
                <th><a href='imdb.php?order=case<?=$sortDirectionCase?>' style='color: white'>Case</a></th>
                <th><a href='imdb.php?order=index<?=$sortDirectionIndex?>' style='color: white'>Index</a></th>
            </tr>
            <?php
                while($row = getNextRowAssoc())
                {
                    ?>
                    <tr>
                        <td><?=$row['name']?></td>
                        <td><?=rawurldecode($row['case'])?></td>
                        <td><?=$row['index']?></td>
                    </tr>
                    <?php
                }
            ?>
            </table>
            <?php
            }
            ?>
        </div>
        <?php

    }
    //print $data->also_known_as . "<br />\n";

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

    function makeIMDBJSONRequest($args)
    {
        // jSON URL which should be requested
        $json_url = 'http://www.imdb.com/xml/';
        $json_query_string = "";

        foreach ($args as $key => $val)
        {
            $json_query_string .= rawurlencode($key) . "=" . rawurlencode($val) . "&";
        }

        $json_query_string = rtrim($json_query_string, "&");

        // Initializing curl
        $ch = curl_init();

        // Configuring curl options
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $json_url . "?" . $json_query_string,
        );

        // Setting curl options
        curl_setopt_array( $ch, $options );

        // Getting results
        $result =  curl_exec($ch); // Getting jSON result string

        $result = json_decode($result);

        return $result;
    }

    function makeOMDBJSONRequest($args)
    {
        // jSON URL which should be requested
        $json_url = 'http://www.omdbapi.com/';
        $json_query_string = "";

        foreach ($args as $key => $val)
        {
            $json_query_string .= rawurlencode($key) . "=" . rawurlencode($val) . "&";
        }

        $json_query_string = rtrim($json_query_string, "&");

        // Initializing curl
        $ch = curl_init();

        // Configuring curl options
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $json_url . "?" . $json_query_string,
        );

        // Setting curl options
        curl_setopt_array( $ch, $options );

        // Getting results
        $result =  curl_exec($ch); // Getting jSON result string
        return json_decode($result);
    }

    function insertIntoSingleFieldTable($table, $column, $value)
    {
        global $dbh;

        $row = getRecord($table, $column, $value);

        if (!$row)
        {
            $query = "INSERT INTO $table ($column) VALUES ($1) RETURNING id";
            $result = pg_query_params($dbh, $query, array(pg_escape_string($value)));

            if ($result) $row = pg_fetch_assoc($result);
            else return false;
        }

        return $row['id'];
    }

    function insertEntry($data)
    {
        global $dbh;

        $row = getRecord("meta_data", "imdb_id", $data->imdbID);

        if ($row)
        {
            return $row['id'];
        }

        $query = "INSERT INTO meta_data (title, year, rating, age_rating_id, poster, imdb_url, imdb_id, rating_count, plot, type_id) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10) returning id";

        $params = array(
            $data->Title,
            $data->Year,
            $data->imdbRating,
            $data->rated_id,
            $data->Poster,
            $data->imdb_url,
            $data->imdbID,
            intval(str_replace(",", "", $data->imdbVotes)),
            $data->Plot,
            $data->type_id,
        );

        for ($i=0; $i < count($params); $i++) {
            if ($params[$i] == 'N/A') {
                $params[$i] = null;
            }
        }

        $result = pg_query_params($dbh, $query, $params);

        if ($result) $row = pg_fetch_assoc($result);
        else return false;

        return $row['id'];
    }

    function updateDisc($disc, $entry)
    {
        global $dbh;

        $query = "UPDATE discs SET meta_data_id = $1 WHERE id = $2";
        if (!pg_query_params($dbh, $query, array($entry, $disc)))
        {
            printError("Couldn't update disc $disc to be associated with meta data $entry");
            return false;
        }
        else
        {
            return true;
        }

    }

    function addManyToManyKeys($entry, $genres, $actors)
    {
        global $dbh;

        $errors = 0;

        foreach ($genres as $genre)
        {
            if (startMultiRowLookup("SELECT * FROM meta_genres WHERE meta_data_id = $entry AND genre_id = $genre"))
            {
                if (numRows() == 0)
                {
                    $query = "INSERT INTO meta_genres (meta_data_id, genre_id) VALUES ($1, $2)";
                    if (!pg_query_params($dbh, $query, array($entry, $genre)))
                    {
                        printError("Couldn't add relationship between Meta Data ID $entry and genre $genre");
                        $errors++;
                    }
                }
            }
            else
            {
                printError("Couldn't perform meta_genres lookup");
                $errors++;
            }
        }

        foreach ($actors as $actor)
        {
            if (startMultiRowLookup("SELECT * FROM meta_actors WHERE meta_data_id = $entry AND actor_id = $actor"))
            {
                if (numRows() == 0)
                {
                    $query = "INSERT INTO meta_actors (meta_data_id, actor_id) VALUES ($1, $2)";
                    if (!pg_query_params($dbh, $query, array($entry, $actor)))
                    {
                        printError("Couldn't add relationship between Meta Data ID $entry and Actor $actor");
                        $errors++;
                    }
                }
            }
            else
            {
                printError("Couldn't perform meta_actors lookup");
                $errors++;
            }
        }

        if ($errors == 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }


    function insertGenre($genre)
    {
        $id = insertIntoSingleFieldTable("genres", "genre", $genre);

        if (!$id)
        {
            print "Unable to insert genre $genre, error: " . pg_last_error() . "<br />\n";
            return false;
        }

        return $id;
    }

    function insertActor($actor)
    {
        $id = insertIntoSingleFieldTable("actors", "actor", $actor);

        if (!$id)
        {
            print "Unable to insert actor $actor, error: " . pg_last_error() . "<br />\n";
            return false;
        }

        return $id;
    }

    function insertAgeRating($rating)
    {
        $id = insertIntoSingleFieldTable("age_ratings", "rating", $rating);

        if (!$id)
        {
            print "Unable to insert age rating $rating, error: " . pg_last_error() . "<br />\n";
            return false;
        }

        return $id;
    }


    function getTitleOptions()
    {
        startMultiRowLookup("SELECT id, title, year FROM meta_data ORDER BY title");
        
        $options = array();

        while ($row = getNextRowAssoc())
        {
            $options[$row['id']] = array("title" => $row['title'], "year" => $row['year']);
        }

        return $options;
    }

    function getPoster($imdb_id, $poster_url)
    {
        if (!file_exists("posters/$imdb_id.jpg"))
        {
            $destination = "posters/$imdb_id.jpg";
            $file = fopen($destination, 'wb');

            $ch = curl_init($poster_url);

            curl_setopt($ch, CURLOPT_FILE, $file);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_exec ($ch);
            curl_close ($ch);

            fclose($file);
        }
    }

    function insertAKA($entry, $aka)
    {
        global $dbh;
        $errors = 0;

        foreach ($aka as $name)
        {
            $select_query = "SELECT * FROM also_known_as WHERE aka = $1 AND meta_data_id = $2";
            $result = pg_query_params($dbh,$select_query,array($name, $entry));
            if (pg_num_rows($result) == 0)
            {
                $query = "INSERT INTO also_known_as (aka, meta_data_id) VALUES ($1, $2)";
                $result = pg_query_params($dbh, $query, array($name, $entry));
                if (!$result)
                {
                    printError("Couldn't insert AKA ($aka) for entry $entry");
                    $errors++;
                }
            }
        }

        if ($errors == 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function insertType($code)
    {
        global $dbh;

        $select_query = "SELECT * FROM types WHERE code = $1";
        $result = pg_query_params($dbh,$select_query,array($code));

        if (pg_num_rows($result) == 0)
        {
            $query = "INSERT INTO types (code) VALUES ($1) RETURNING id";
            $result = pg_query_params($dbh, $query, array($code));
            if (!$result)
            {
                printError("Couldn't insert AKA ($aka) for entry $entry");
                return false;
            }
            else
            {
                $row = pg_fetch_assoc($result);
                return $row['id'];
            }
        }

        $row = pg_fetch_assoc($result);
        return $row['id'];
    }

    function markDiscToIgnore($disc)
    {
        return changeIgnoreStatus($disc, "TRUE");
    }

    function unignoreDisc($disc)
    {
        return changeIgnoreStatus($disc, "FALSE");
    }

    function changeIgnoreStatus($disc, $status)
    {
        global $dbh;
        if (preg_match("/^[0-9]+$/", $disc) && $disc >= 0 && ($status == "FALSE" || $status == "TRUE"))
        {
            $query = "UPDATE discs SET skip_meta_data = $1 WHERE id = $2";

            if (pg_query_params($dbh,$query,array($status, $disc)))
            {
                return true;
            }
        }
        return false;
    }

    function resetSkip()
    {
        global $dbh;
        
        $query = "UPDATE discs SET skip_meta_data = FALSE";
        if (pg_query($dbh, $query))
        {
            return true;
        }

        return false;
    }

    function generateSearchVectors($disc_id) {
        global $dbh;

        $query = "SELECT generatesearchvectors2($1)";

        $result = pg_query_params($dbh, $query, array($disc_id));

        if ($result) $row = pg_fetch_assoc($result);
        else {
            print pg_last_error();
            return false;
        }

        return $row['generatesearchvectors2'];
    }
?>
</div>
<div class="footer">
    &copy; Copyright Jonathan Harden 2016 -
    <a href='https://secure.eviljonnys.com/evilindexer/'>Admin</a> :
    <a href='index.php'>Frontend</a>
</div>
</body>
</html>
