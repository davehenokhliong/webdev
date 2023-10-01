<?php
    define("DB_HOST", "mydb");
    define("USERNAME", "dummy");
    define("PASSWORD", "c3322b");
    define("DB_NAME", "db3322");
    $conn=mysqli_connect(DB_HOST, USERNAME, PASSWORD, DB_NAME) or die('Error! '. mysqli_connect_error($conn));

    $uid = mysqli_real_escape_string($conn, $_GET['uid']);
    $course = mysqli_real_escape_string($conn, $_GET['course']);

    //detect if time surpass 300 seconds
    $query = "SELECT * FROM user WHERE uid='$uid'";
    $result = mysqli_query($conn, $query) or die ('Failed to query ' . mysqli_error($conn));
    $time = mysqli_fetch_array($result)['timestamp'];

    if ($time + 300 < time()){
        header('location: ../login.php');
        exit();
    }

    //print the scores and total
    $query = "SELECT * FROM courseinfo WHERE uid='$uid' AND course='$course'";
    $result = mysqli_query($conn, $query) or die ('Failed to query ' . mysqli_error($conn));

    echo '<style>table.scores {border-collapse: collapse; margin: auto; text-align: center;} table.scores td, table.scores th {border: 1px solid black; padding: 5px;} </style>';
    echo '<h1>' . $course . ' - Gradebook</h1>';

    if (mysqli_num_rows($result) == 0 ) {
        echo '<p>You do not have the gradebook for the course ' . $course . ' in the system.</p>';
    } else {
        echo '<p>Assessment Scores:</p> 
                <table class="scores"> 
                    <thead><tr>
                    <th>Item</th> 
                    <th>Score</th> 
                    </tr></thead>
                    <tbody>';
        $total = 0;
        while ($row = mysqli_fetch_array($result)) {
            if ($row['course'] == $course) {
                $total += $row['score'];
                echo '<tr><td>' . $row['assign'] . '</td><td>' . $row['score'] . '</td></tr>';
            }
        }
        echo '<tr><td></td><td>Total: ' . $total . '</td></tr></tbody></table>';
    }

    mysqli_close($conn);
?>