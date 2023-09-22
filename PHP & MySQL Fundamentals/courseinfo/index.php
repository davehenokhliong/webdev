<?php
    define("DB_HOST", "mydb");
    define("USERNAME", "dummy");
    define("PASSWORD", "c3322b");
    define("DB_NAME", "db3322");
    $conn=mysqli_connect(DB_HOST, USERNAME, PASSWORD, DB_NAME) or die('Error! '. mysqli_connect_error($conn));

    $uid = mysqli_real_escape_string($conn, $_GET['uid']);

    //detect if time surpass 300 seconds
    $query = "SELECT * FROM user WHERE uid='$uid'";
    $result = mysqli_query($conn, $query) or die ('Failed to query ' . mysqli_error($conn));
    $time = mysqli_fetch_array($result)['timestamp'];

    if ($time + 300 < time()){ //ganti ya
        header('location: ../login.php');
        exit();
    }

    //print the courses
    $query = "SELECT DISTINCT course FROM courseinfo WHERE uid='$uid'";
    $result = mysqli_query($conn, $query) or die ('Failed to query ' . mysqli_error($conn));

    echo '<h1><b>Course Information</b></h1>';
    echo '<h3><b>Retrieve continuous assessment scores for:</b></h3>';
    echo '<p id="entries"></p>';

    $courses = array();
    while ($row = mysqli_fetch_array($result)) {
        if (! in_array($row['course'], $courses)) {
            $courses[] = $row['course'];
            echo '<a href="getscore.php?uid=' . $uid . '&course=' . $row['course'] . '">' . $row['course'] . '</a><br>';
        }
    }

    mysqli_close($conn);

?>