<?php
// Database connection details
$host = "studdb.csc.liv.ac.uk";
$username = "sgadeshp";
$password = "aesthe25A";
$database = "sgadeshp";

// Connect to the database
try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to validate name
function validateName($name) {
    $pattern = '/^[a-zA-Z\'\- ]+$/';
    $pattern2 = '/[\']{2,}|[-]{2,}/';
    $pattern3 = '/^[\']/';
    $pattern4 = '/[ -]$/';
    return preg_match($pattern, $name) &&
           !preg_match($pattern2, $name) &&
           !preg_match($pattern3, $name) &&
           !preg_match($pattern4, $name);
}

// Function to validate email
function validateEmail($email) {
    $pattern = '/^[\w\.\-]+@[\w\.\-]+\.\w+$/';
    return preg_match($pattern, $email);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $topic = $_POST["topic"];
    $session = $_POST["session"];
    $name = $_POST["name"];
    $email = $_POST["email"];

    // Validate name and email
    if (!validateName($name)) {
        $errorMessage = "Invalid name format. Please enter a valid name.";
    } elseif (!validateEmail($email)) {
        $errorMessage = "Invalid email format. Please enter a valid email address.";
    } else {
        try {
            // Check if session has available capacity
            $stmt = $conn->prepare("SELECT Capacity FROM Sessions WHERE SessionID = :sessionId");
            $stmt->bindParam(":sessionId", $session);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $capacity = $result['Capacity'];

            if ($capacity > 0) {
                // Insert booking into the database
                $stmt = $conn->prepare("INSERT INTO Bookings (SessionID, Name, Email) VALUES (:sessionId, :name, :email)");
                $stmt->bindParam(":sessionId", $session);
                $stmt->bindParam(":name", $name);
                $stmt->bindParam(":email", $email);
                $stmt->execute();

                // Update session capacity
                $stmt = $conn->prepare("UPDATE Sessions SET Capacity = Capacity - 1 WHERE SessionID = :sessionId");
                $stmt->bindParam(":sessionId", $session);
                $stmt->execute();

                $successMessage = "Booking successful!";
            } else {
                $errorMessage = "Sorry, the selected session is full.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}

// Fetch topics and sessions from the database
$topics = $conn->query("SELECT TopicID, TopicName FROM Topics")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $conn->query("SELECT SessionID, TopicID, Day, TIME_FORMAT(Time, '%h:%i %p') AS Time, Capacity FROM Sessions")->fetchAll(PDO::FETCH_ASSOC);

// Fetch bookings from the database
$bookings = $conn->query("SELECT t.TopicName, s.Day, TIME_FORMAT(s.Time, '%h:%i %p') AS Time, b.Name, b.Email
                          FROM Bookings b
                          JOIN Sessions s ON b.SessionID = s.SessionID
                          JOIN Topics t ON s.TopicID = t.TopicID")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Training Session Booking</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function updateSessions(topicId) {
            var sessionSelect = document.getElementById("session");
            sessionSelect.innerHTML = ""; // Clear existing option
            var sessions = <?php echo json_encode($sessions); ?>;
            sessions.forEach(function(session) {
                if (session.TopicID == topicId) {
                    var option = document.createElement("option");
                    option.value = session.SessionID;
                    option.text = session.Day + ", " + session.Time + " (Capacity: " + session.Capacity + ")";
                    sessionSelect.add(option);
                }
            });
        }
    </script>
</head>
<body>
    <h1>Training Session Booking</h1>

    <?php if (isset($errorMessage)) { ?>
        <p style="color: red;"><?php echo $errorMessage; ?></p>
    <?php } ?>

    <?php if (isset($successMessage)) { ?>
        <p style="color: green;"><?php echo $successMessage; ?></p>
    <?php } ?>

    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <label for="topic">Select Topic:</label>
        <select id="topic" name="topic" onchange="updateSessions(this.value)">
            <?php foreach ($topics as $topic) { ?>
                <option value="<?php echo $topic['TopicID']; ?>"><?php echo $topic['TopicName']; ?></option>
            <?php } ?>
        </select>
        <br><br>

        <label for="session">Select Session:</label>
        <select id="session" name="session">
            
        </select>
        <br><br>

        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>
        <br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br><br>

        <input type="submit" value="Submit">
    </form>

    <h2>Bookings:</h2>
    <table>
        <tr>
            <th>Topic</th>
            <th>Day</th>
            <th>Time</th>
            <th>Name</th>
            <th>Email</th>
        </tr>
        <?php foreach ($bookings as $booking) { ?>
            <tr>
                <td><?php echo $booking['TopicName']; ?></td>
                <td><?php echo $booking['Day']; ?></td>
                <td><?php echo $booking['Time']; ?></td>
                <td><?php echo $booking['Name']; ?></td>
                <td><?php echo $booking['Email']; ?></td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>