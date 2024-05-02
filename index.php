<?php

include_once "functions.php";

// checkCompletedJobs();
// processTask($apiKey);

$jobs = getJobs();

$error = "";
$message = "";

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $target_dir = $root_path . "files/";
    $target_file = $target_dir . basename($_FILES["file"]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if file already exists
    if (file_exists($target_file)) {
        $error = "Sorry, file already exists.";
        $uploadOk = 0;
    }

    // Allow only Excel files
    if ($fileType != "xlsx" && $fileType != "xls") {
        $error = "Sorry, only Excel files are allowed.";
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        // echo "Sorry, your file was not uploaded.";
    } else {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            $message = "The file " . basename($_FILES["file"]["name"]) . " has been uploaded.";
            // Insert data into tasks table
            insertTasksFromExcel($target_file);
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    }
}


?>



<!DOCTYPE html>
<html>

<head>
    <title>ChatGPT Image Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" /> -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <br />
        <h3 align="center">Upload Excel File</h3>
        <div class="table-responsive">
            <form action="index.php" method="post" enctype="multipart/form-data">
                <?php if (!empty($error)) : ?>
                    <p class="text-center mt-2 text-danger"><?php echo $error; ?></p>
                <?php elseif (!empty($message)) : ?>
                    <p class="text-center mt-2 text-success"><?php echo $message; ?></p>
                <?php endif; ?>
                <table class="table mt-4">
                    <tr>
                        <td width="25%" align="right"></td>
                        <td width="50%"><input type="file" name="file" /></td>
                        <td width="25%"><input type="submit" name="submit" class="btn btn-primary" /></td>
                    </tr>
                </table>
            </form>
            <br />
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th scope="col">Sr#</th>
                    <th scope="col">Job Name</th>
                    <th scope="col">Status</th>
                    <th scope="col">Download Zip</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($jobs)) : ?>
                    <?php foreach ($jobs as $count => $job) : ?>
                        <tr>
                            <th scope="row"><?php echo ++$count; ?></th>
                            <td><?php echo basename($job->file, ".zip"); ?></td>
                            <td class="<?php echo $job->status == 0 ? 'text-dark' : 'text-white'; ?>">
                                <div class="<?php echo $job->status == 0 ? 'bg-warning' : 'bg-success'; ?> px-3 py-1 text-center" style="border-radius: 10px; width: fit-content; font-weight: 600;">
                                    <?php echo $job->status == 0 ? 'Processing' : 'Processed'; ?>
                                </div>
                            </td>
                            <td><a href="<?php echo "zip_files/" . $job->file; ?>">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>