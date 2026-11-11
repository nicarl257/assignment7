<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectToList(): void
{
    header('Location: index.php');
    exit;
}

$errors = [];
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

$form = [
    'name' => '',
    'registration_number' => '',
    'email' => '',
    'course' => '',
];
$editingStudent = null;
$students = [];
$databaseError = null;

try {
    $pdo = require __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $submittedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            $errors[] = 'Your session has expired. Refresh the page and try again.';
        } elseif ($action === 'delete') {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId === null || $studentId < 1) {
                $errors[] = 'The selected student is invalid.';
            } else {
                $statement = $pdo->prepare('DELETE FROM students WHERE id = :id');
                $statement->execute(['id' => $studentId]);
                $_SESSION['notice'] = $statement->rowCount() > 0
                    ? 'Student deleted.'
                    : 'That student could not be found.';
                redirectToList();
            }
        } elseif ($action === 'add' || $action === 'update') {
            foreach ($form as $field => $value) {
                $postedValue = $_POST[$field] ?? '';
                $form[$field] = is_string($postedValue) ? trim($postedValue) : '';
            }

            foreach ([
                'name' => 'Student name',
                'registration_number' => 'Registration number',
                'email' => 'Email address',
                'course' => 'Course',
            ] as $field => $label) {
                if ($form[$field] === '') {
                    $errors[] = $label . ' is required.';
                }
            }

            if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
            }

            $studentId = null;
            if ($action === 'update') {
                $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
                if ($studentId === false || $studentId === null || $studentId < 1) {
                    $errors[] = 'The selected student is invalid.';
                } else {
                    $statement = $pdo->prepare('SELECT id FROM students WHERE id = :id');
                    $statement->execute(['id' => $studentId]);
                    $editingStudent = $statement->fetch();
                    if (!$editingStudent) {
                        $errors[] = 'That student could not be found.';
                    }
                }
            }

            if (!$errors) {
                $values = [
                    'name' => $form['name'],
                    'registration_number' => $form['registration_number'],
                    'email' => $form['email'],
                    'course' => $form['course'],
                ];

                try {
                    if ($action === 'add') {
                        $statement = $pdo->prepare(
                            'INSERT INTO students (name, registration_number, email, course)
                             VALUES (:name, :registration_number, :email, :course)'
                        );
                        $statement->execute($values);
                        $_SESSION['notice'] = 'Student added.';
                    } else {
                        $values['id'] = $studentId;
                        $statement = $pdo->prepare(
                            'UPDATE students
                             SET name = :name, registration_number = :registration_number,
                                 email = :email, course = :course
                             WHERE id = :id'
                        );
                        $statement->execute($values);
                        $_SESSION['notice'] = 'Student updated.';
                    }

                    redirectToList();
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $errors[] = 'That registration number is already in use.';
                    } else {
                        throw $exception;
                    }
                }
            }
        } else {
            $errors[] = 'The requested action is not valid.';
        }
    }

    if (!$errors && isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
        if ($editId === false || $editId === null || $editId < 1) {
            $errors[] = 'The selected student is invalid.';
        } else {
            $statement = $pdo->prepare('SELECT * FROM students WHERE id = :id');
            $statement->execute(['id' => $editId]);
            $editingStudent = $statement->fetch();
            if ($editingStudent) {
                foreach ($form as $field => $_) {
                    $form[$field] = (string) $editingStudent[$field];
                }
            } else {
                $errors[] = 'That student could not be found.';
            }
        }
    }

    $students = $pdo->query(
        'SELECT id, name, registration_number, email, course
         FROM students
         ORDER BY id DESC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = 'Could not connect to the database. Check your MySQL service and database settings.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="page">
        <header class="page-header">
            <p class="eyebrow">Student records</p>
            <h1>Student Management</h1>
            <p class="subtitle">Add, update, and manage student information.</p>
        </header>

        <?php if ($databaseError !== null): ?>
            <div class="alert alert-error" role="alert"><?= escape($databaseError) ?></div>
        <?php endif; ?>

        <?php if ($notice !== null): ?>
            <div class="alert alert-success" role="status"><?= escape((string) $notice) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($databaseError === null): ?>
            <section class="panel form-panel" aria-labelledby="form-title">
                <div class="section-heading">
                    <div>
                        <h2 id="form-title"><?= $editingStudent ? 'Update student' : 'Register a student' ?></h2>
                        <p>Enter the student details below.</p>
                    </div>
                </div>

                <form method="post" action="index.php" class="student-form">
                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="<?= $editingStudent ? 'update' : 'add' ?>">
                    <?php if ($editingStudent): ?>
                        <input type="hidden" name="student_id" value="<?= (int) $editingStudent['id'] ?>">
                    <?php endif; ?>

                    <div class="field">
                        <label for="name">Student name</label>
                        <input id="name" name="name" type="text" maxlength="120" required
                               value="<?= escape($form['name']) ?>" placeholder="e.g. Alex Morgan">
                    </div>
                    <div class="field">
                        <label for="registration_number">Registration number</label>
                        <input id="registration_number" name="registration_number" type="text" maxlength="40" required
                               value="<?= escape($form['registration_number']) ?>" placeholder="e.g. STU-2026-001">
                    </div>
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" maxlength="190" required
                               value="<?= escape($form['email']) ?>" placeholder="student@example.com">
                    </div>
                    <div class="field">
                        <label for="course">Course</label>
                        <input id="course" name="course" type="text" maxlength="120" required
                               value="<?= escape($form['course']) ?>" placeholder="e.g. Information Technology">
                    </div>
                    <div class="form-actions">
                        <button class="button button-primary" type="submit">
                            <?= $editingStudent ? 'Save changes' : 'Add student' ?>
                        </button>
                        <?php if ($editingStudent): ?>
                            <a class="button button-secondary" href="index.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel list-panel" aria-labelledby="students-title">
                <div class="section-heading">
                    <div>
                        <h2 id="students-title">Registered students</h2>
                        <p><?= count($students) ?> <?= count($students) === 1 ? 'student' : 'students' ?></p>
                    </div>
                </div>

                <?php if (!$students): ?>
                    <p class="empty-state">No student records yet. Add the first student using the form above.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Registration no.</th>
                                    <th>Email</th>
                                    <th>Course</th>
                                    <th class="actions-heading">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td data-label="Name"><?= escape((string) $student['name']) ?></td>
                                        <td data-label="Registration no."><?= escape((string) $student['registration_number']) ?></td>
                                        <td data-label="Email"><?= escape((string) $student['email']) ?></td>
                                        <td data-label="Course"><?= escape((string) $student['course']) ?></td>
                                        <td data-label="Actions" class="row-actions">
                                            <a class="text-button" href="index.php?edit=<?= (int) $student['id'] ?>">Edit</a>
                                            <form method="post" action="index.php" onsubmit="return confirm('Delete this student record?');">
                                                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                                <button class="text-button text-button-danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <footer class="page-footer">Student Management System</footer>
    </main>
</body>
</html>
