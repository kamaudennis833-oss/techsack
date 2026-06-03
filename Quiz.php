<?php
session_start();
include 'db.php';

/* 
   CREATE QUIZ
 */

if(isset($_POST['create_quiz']))
{
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $passing_marks = intval($_POST['passing_marks']);
    $duration = intval($_POST['duration']);

    $stmt = $conn->prepare("
    INSERT INTO quizzes
    (course_id,title,description,passing_score,duration)
    VALUES (?,?,?,?,?)
    ");

    $stmt->bind_param(
        "issii",
        $course_id,
        $title,
        $description,
        $passing_marks,
        $duration
    );

    $stmt->execute();
}

/* 
   ADD QUESTION
 */

if(isset($_POST['add_question']))
{
    $quiz_id = intval($_POST['quiz_id']);
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];

    $option_a = $_POST['option_a'] ?? null;
    $option_b = $_POST['option_b'] ?? null;
    $option_c = $_POST['option_c'] ?? null;
    $option_d = $_POST['option_d'] ?? null;

    $correct_answer = trim($_POST['correct_answer']);
    $marks = intval($_POST['marks']);

    /* 
        CHECK QUIZ EXISTS
     */
    $check = mysqli_query($conn, "
        SELECT id FROM quizzes WHERE id = '$quiz_id'
    ");

    if(mysqli_num_rows($check) == 0){
        die("❌ Error: Selected quiz does not exist.");
    }

    /* 
       INSERT QUESTION
     */
    $stmt = $conn->prepare("
        INSERT INTO quiz_questions
        (
            quiz_id,
            question,
            question_type,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_answer,
            marks
        )
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "isssssssi",
        $quiz_id,
        $question,
        $question_type,
        $option_a,
        $option_b,
        $option_c,
        $option_d,
        $correct_answer,
        $marks
    );

    if($stmt->execute()){
        echo "✅ Question added successfully";
    } else {
        echo "❌ Error inserting question";
    }
}



/* 
   DASHBOARD STATS
 */

$total_quizzes = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quizzes")
)['total'] ?? 0;

$total_questions = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_questions")
)['total'] ?? 0;

$total_attempts = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts")
)['total'] ?? 0;

$passed = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts
WHERE result='Pass'")
)['total'] ?? 0;

$failed = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts
WHERE result='Fail'")
)['total'] ?? 0;

$average_score = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT AVG(score) avg_score
FROM quiz_attempts")
)['avg_score'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Quiz Management</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,sans-serif;
}

body{
background:#f4f6f9;
padding:20px;
}

.container{
max-width:1400px;
margin:auto;
}

h1{
margin-bottom:20px;
}

.cards{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
margin-bottom:30px;
}

.card{
background:#fff;
padding:20px;
border-radius:10px;
box-shadow:0 2px 10px rgba(0,0,0,.1);
}

.card h2{
color:#007bff;
margin-bottom:10px;
}

.section{
background:#fff;
padding:20px;
border-radius:10px;
margin-bottom:25px;
box-shadow:0 2px 10px rgba(0,0,0,.1);
}

.section h3{
margin-bottom:15px;
}

input,
textarea,
select{
width:100%;
padding:12px;
margin-bottom:10px;
border:1px solid #ddd;
border-radius:6px;
}

button{
background:#007bff;
color:#fff;
border:none;
padding:12px 20px;
border-radius:6px;
cursor:pointer;
}

button:hover{
background:#0056b3;
}

table{
width:100%;
border-collapse:collapse;
margin-top:15px;
}

table th{
background:#007bff;
color:white;
padding:12px;
text-align:left;
}

table td{
padding:10px;
border-bottom:1px solid #ddd;
}

.pass{
color:green;
font-weight:bold;
}

.fail{
color:red;
font-weight:bold;
}

@media(max-width:768px){
table{
display:block;
overflow-x:auto;
}
}

</style>

</head>

<body>

<div class="container">

<h1>
<i class="fas fa-file-alt"></i>
Quiz Management
</h1>

<!-- STATS -->

<div class="cards">

<div class="card">
<h2><?= $total_quizzes ?></h2>
<p>Total Quizzes</p>
</div>

<div class="card">
<h2><?= $total_questions ?></h2>
<p>Total Questions</p>
</div>

<div class="card">
<h2><?= $total_attempts ?></h2>
<p>Attempts</p>
</div>

<div class="card">
<h2><?= $passed ?></h2>
<p>Passed</p>
</div>

<div class="card">
<h2><?= $failed ?></h2>
<p>Failed</p>
</div>

<div class="card">
<h2><?= round($average_score,2) ?></h2>
<p>Average Score</p>
</div>

</div>

<!-- CREATE QUIZ -->

<div class="section">

<h3>Create Quiz</h3>

<form method="POST">

<select name="course_id" required>

<option value="">Select Course</option>

<?php
$courses = mysqli_query($conn,"SELECT * FROM courses");

while($course=mysqli_fetch_assoc($courses))
{
?>
<option value="<?= $course['id'] ?>">
<?= htmlspecialchars($course['title']) ?>
</option>
<?php } ?>

</select>

<input type="text"
name="title"
placeholder="Quiz Title"
required>

<textarea
name="description"
placeholder="Quiz Description"></textarea>

<input
type="number"
name="passing_marks"
placeholder="Passing Marks (%)"
required>

<input
type="number"
name="duration"
placeholder="Duration (Minutes)"
required>

<button type="submit" name="create_quiz">
Create Quiz
</button>

</form>

</div>

<!-- ADD QUESTION -->

<div class="section">

<h3>Add Question</h3>

<form method="POST">

<select name="quiz_id" required>

<option value="">Select Quiz</option>

<?php
$quiz_list = mysqli_query($conn,"
SELECT *
FROM quizzes
ORDER BY id DESC
");

while($quiz=mysqli_fetch_assoc($quiz_list))
{
?>
<option value="<?= $quiz['id'] ?>">
<?= htmlspecialchars($quiz['title']) ?>
</option>
<?php } ?>

</select>

<textarea
name="question"
placeholder="Enter Question"
required></textarea>

<select name="question_type">

<option value="mcq">
Multiple Choice Question
</option>

<option value="short_answer">
Short Answer
</option>

</select>

<input type="text"
name="option_a"
placeholder="Option A">

<input type="text"
name="option_b"
placeholder="Option B">

<input type="text"
name="option_c"
placeholder="Option C">

<input type="text"
name="option_d"
placeholder="Option D">

<input type="text"
name="correct_answer"
placeholder="Correct Answer"
required>

<input
type="number"
name="marks"
value="1">

<button type="submit" name="add_question">
Add Question
</button>

</form>

</div>

<!-- QUIZZES -->

<div class="section">

<h3>Quiz List</h3>

<table>

<tr>
<th>ID</th>
<th>Course</th>
<th>Quiz</th>
<th>Passing</th>
<th>Duration</th>
<th>Status</th>
</tr>

<?php

$quizzes = mysqli_query($conn,"
SELECT q.*, c.title AS course_name
FROM quizzes q
LEFT JOIN courses c
ON c.id=q.course_id
ORDER BY q.id DESC
");

while($row=mysqli_fetch_assoc($quizzes))
{
?>

<tr>

<td><?= $row['id'] ?></td>
<td><?= $row['course_name'] ?></td>
<td><?= $row['title'] ?></td>
<td><?= $row['passing_marks'] ?>%</td>
<td><?= $row['duration'] ?> mins</td>
<td><?= $row['status'] ?></td>

</tr>

<?php } ?>

</table>

</div>

<!-- STUDENT ATTEMPTS -->

<div class="section">

<h3>Student Quiz Attempts</h3>

<table>

<tr>
<th>Student</th>
<th>Quiz</th>
<th>Score</th>
<th>Percentage</th>
<th>Result</th>
<th>Date</th>
</tr>

<?php

$attempts = mysqli_query($conn,"
SELECT
    qa.*,
    u.full_name,
    q.title AS quiz_title,
    qa.status

FROM quiz_attempts qa

LEFT JOIN users u ON u.id = qa.user_id
LEFT JOIN quizzes q ON q.id = qa.quiz_id

ORDER BY qa.id DESC
");
while($attempt=mysqli_fetch_assoc($attempts))
{
?>

<tr>

<td><?= $attempt['full_name'] ?></td>

<td><?= $attempt['quiz_title'] ?></td>

<td><?= $attempt['score'] ?></td>

<td><?= $attempt['percentage'] ?>%</td>

<td class="<?= strtolower($attempt['result']) ?>">
<?= $attempt['result'] ?>
</td>

<td><?= $attempt['created_at'] ?></td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>