<?php

require_once 'config.php';
require_once 'connection.php';

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function getJobs() {
    global $pdo;

    $conn = $pdo->open();

    $sql = "SELECT * FROM jobs";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $jobs = $stmt->fetchAll();

    return $jobs;
}

function insertTasksFromExcel($file) {
    global $pdo;
    global $error;

    $conn = $pdo->open();

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    // Start a transaction
    $conn->beginTransaction();

    try {
        $query = "SELECT id FROM jobs WHERE status = 0 LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $job = $stmt->fetch();
        if (!empty($job)) {
            $error = "There is already one job pending. Please wait while all the existing jobs are finished.";
            unlink($file);
            return false;
        }

        $sheetName = basename($file);

        // Insert job row
        $insertJobSql = "INSERT INTO jobs (sheet) VALUES (?)";
        $stmt = $conn->prepare($insertJobSql);
        $stmt->execute([$sheetName]);
        $job_id = $conn->lastInsertId();

        // Insert task rows
        for ($row = 1; $row <= $highestRow; $row++) {
            $prompt = $sheet->getCellByColumnAndRow(1, $row)->getValue();
            $insertTaskSql = "INSERT INTO tasks (job_id, prompt, status) VALUES (?,?,?)";
            $stmt = $conn->prepare($insertTaskSql);
            $stmt->execute([$job_id, $prompt, 0]);
        }

        // Commit the transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback the transaction on exception
        $conn->rollback();
        unlink($file);
        $error = "Error: " . $e->getMessage();
    }
}

// Function to process one pending task
function processTask($apiKey) {
    global $pdo;

    $conn = $pdo->open();

    // Select one pending task
    $sql = "SELECT * FROM tasks WHERE status = 0 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $task = $stmt->fetch();

    if ($task) {
        // Call OpenAI API to generate image
        $imageURL = generateImage($task->prompt, $apiKey);

        // Save the image URL and download the image
        $imagePath = saveImage($imageURL);
        $imageName = !empty($imagePath) ? basename($imagePath) : null;

        if ($imagePath) {
            // Update task with image URL and status
            $updateSql = "UPDATE tasks SET img = ?, status = ? WHERE id = " . $task->id;
            $stmt = $conn->prepare($updateSql);
            $stmt->execute([$imageName, 1]);

            // Check if all tasks for this job are completed
            $job_id = $task->job_id;
            $pendingTasksSql = "SELECT * FROM tasks WHERE job_id = $job_id AND status = 0";
            $stmt = $conn->prepare($pendingTasksSql);
            $stmt->execute();
            $pendingTasks = $stmt->fetch();

            if (!$pendingTasks) {
                // Generate zip file of images for this job
                generateZip($job_id);
            }
        }
    }
}

$count = 0;
function generateImage($prompt, $apiKey) {
    // global $count;
    // Check if the prompt is empty
    if (empty($prompt)) {
        return null;
    }

    // $images = [
    //     "https://cdn.pixabay.com/photo/2015/04/23/22/00/tree-736885_640.jpg",
    //     "https://img.freepik.com/free-photo/colorful-design-with-spiral-design_188544-9588.jpg?size=626&ext=jpg&ga=GA1.1.1224184972.1714435200&semt=sph"
    // ];

    // if ($count < 2) {
    //     return $images[$count];
    // } else {
    //     return null;
    // }

    // $count++;

    // API endpoint
    $url = 'https://api.openai.com/v1/images/generations';

    // Data to send to the API
    $data = array(
        'model' => 'dall-e-3', // ChatGPT-4 model
        'prompt' => $prompt,
        'size' => "1024x1024",
        'quality' => "standard",
        'n' => 1,
    );

    // Set up cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));

    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if ($response === false) {
        return null;
    }

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    $result = json_decode($response, true);

    // Check if there's an error in the response
    if (!empty($result['error'])) {
        return null;
    }

    // Extract the generated image URL from the response
    $imageURL = !empty($result['data'][0]['url']) ? $result['data'][0]['url'] : null;

    // Return the image URL
    return $imageURL;
}

function saveImage($imageURL) {
    global $root_path;

    if (empty($imageURL)) {
        return null;
    }

    $directory = $root_path . "images/";
    // $filename = basename($imageURL);
    // $filename = uniqid() . ".jpg";
    $path = parse_url($imageURL, PHP_URL_PATH);
    $filename = pathinfo($path, PATHINFO_BASENAME);
    $filePath = $directory . $filename;

    $imageData = file_get_contents($imageURL);

    if ($imageData === false) {
        echo "Failed to download the image.";
        return false;
    }

    if (!empty($imageData) && file_put_contents($filePath, $imageData) !== false) {
        return $filePath;
    } else {
        echo "Failed to save the image.";
        return false;
    }
}

function generateZip($job_id) {
    global $pdo;
    global $root_path;

    $conn = $pdo->open();

    // Fetch all images associated with the given job ID
    $sql = "SELECT img FROM tasks WHERE job_id = :job_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':job_id', $job_id);
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($images) > 0) {
        // Create a new ZipArchive instance
        $zip = new ZipArchive();

        $sql = "SELECT sheet FROM jobs WHERE id = :job_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        $job = $stmt->fetch();

        if ($job) {
            $zipFileName = $root_path . "zip_files/" . pathinfo($job->sheet, PATHINFO_FILENAME) . ".zip";
            $zipFileNameDB = pathinfo($job->sheet, PATHINFO_FILENAME) . ".zip";

            $allFilesAdded = true;

            // Open the zip file for writing
            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Add each image file to the zip archive
                foreach ($images as $image) {
                    if (empty($image)) {
                        continue;
                    }
                    $imagePath = $root_path . "images/" . $image;
                    // Add the image to the zip file with its original filename
                    if (!$zip->addFile($imagePath, $image)) {
                        $allFilesAdded = false;
                    }
                }

                // Close the zip archive
                $zip->close();

                if ($allFilesAdded) {
                    $status = 1;

                    $mailSent = sendMail(pathinfo($job->sheet, PATHINFO_FILENAME), $zipFileNameDB);

                    if ($mailSent) {
                        // Update the jobs table with the filename of the generated zip file
                        $updateJobSql = "UPDATE jobs SET file = :zipFileName, status = :status WHERE id = :job_id";
                        $stmt = $conn->prepare($updateJobSql);
                        $stmt->bindParam(':zipFileName', $zipFileNameDB);
                        $stmt->bindParam(':status', $status);
                        $stmt->bindParam(':job_id', $job_id);
                        $stmt->execute();

                        echo "Zip file generated successfully.";
                    }
                }
            } else {
                echo "Failed to create the zip file.";
            }
        }
    } else {
        echo "No images found for job ID: " . $job_id;
    }
}

function checkCompletedJobs() {
    global $pdo;

    $conn = $pdo->open();

    // Select jobs with status 0
    $sql = "SELECT id FROM jobs WHERE status = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($jobs)) {
        foreach ($jobs as $job) {
            $job_id = $job['id'];

            // Check if all tasks for this job are completed
            $pendingTasksSql = "SELECT COUNT(*) as pending_count FROM tasks WHERE job_id = :job_id AND status = 0";
            $stmt = $conn->prepare($pendingTasksSql);
            $stmt->bindParam(':job_id', $job_id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $pending_count = $row['pending_count'];

            if ($pending_count == 0) {
                // Generate zip file for this job
                generateZip($job_id);
            }
        }
    }
}

function sendMail($fileName, $filePath) {
    global $smtp_host;
    global $smtp_port;
    global $smtp_username;
    global $smtp_password;
    global $mail_to;

    //Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = $smtp_host;                     //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = $smtp_username;                     //SMTP username
        $mail->Password   = $smtp_password;                               //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
        $mail->Port       = $smtp_port;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        //Recipients
        $mail->setFrom($smtp_username, 'Jesus4all');
        $mail->addAddress($mail_to);
        $mail->addReplyTo($smtp_username);
        $mail->addCC($smtp_username);
        $mail->addBCC($smtp_username);

        //Attachments
        // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = $fileName;
        $mail->Body    = "The job <b>$fileName</b> is finished! Use the following link to download the zip file.<br>
            <a href='https://imagine.chronicles100.com/zip_files/$filePath'>https://imagine.chronicles100.com/zip_files/$filePath</a>";

        if ($mail->send()) {
            return true;
        }
    } catch (Exception $e) {
        echo $e->getMessage();
        return false;
    }
}
