<?php
// Set error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to handle the upload and processing of the add-on
function handleUpload() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['addonFile'])) {
        if ($_FILES['addonFile']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['message' => 'File upload error.']);
            exit;
        }

        // Check file type
        if (pathinfo($_FILES['addonFile']['name'], PATHINFO_EXTENSION) !== 'zip') {
            echo json_encode(['message' => 'Invalid file type. Only ZIP files are allowed.']);
            exit;
        }

        $uploadDir = __DIR__ . '/repo/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $tempZipPath = $uploadDir . 'temp.zip';
        if (!move_uploaded_file($_FILES['addonFile']['tmp_name'], $tempZipPath)) {
            echo json_encode(['message' => 'Failed to move uploaded file.']);
            exit;
        }

        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($tempZipPath) === TRUE) {
            $zip->extractTo($uploadDir);
            $zip->close();
            unlink($tempZipPath); // Remove temp zip
            createAddonsXml(__DIR__ . '/repo/', __DIR__ . '/repo/addons.xml'); // Update addons.xml after deletion

            echo json_encode(['message' => 'Upload successful!']);
        } else {
            echo json_encode(['message' => 'Failed to extract ZIP file.']);
        }
        exit;
    }
}

function saveRepoSettings($settings) {
    $filePath = __DIR__ . '/repo_settings.json';
    $json = json_encode($settings);
    file_put_contents($filePath, $json);
}

function addFolderToZip($folder, &$zipFile, $baseDir) {
    $handle = opendir($folder);
    if ($handle === false) {
        return; // Handle error if the directory cannot be opened
    }
    
    while (false !== ($f = readdir($handle))) {
        if ($f != '.' && $f != '..') {
            $filePath = "$folder/$f";
            $localPath = "$baseDir/$f"; // Use baseDir to create the correct local path

            if (is_file($filePath) && strpos($filePath, '.zip') === false) {
                $zipFile->addFile($filePath, $localPath);
            } elseif (is_dir($filePath)) {
                $zipFile->addEmptyDir($localPath);
                addFolderToZip($filePath, $zipFile, "$baseDir/$f"); // Pass the updated base directory
            }
        }
    }
    
    closedir($handle);
}




// Function to handle the repository setup
function handleRepoSetup() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect POST data
        $addonId = $_POST['addonId'];
        $repoName = $_POST['repoName'];
        $version = $_POST['version'];
        $providerName = $_POST["providerName"];
        // Create the directory for the add-on
        $addonDir = __DIR__ . "/repo/$addonId/";
        $settings = [
            'addonId' => $addonId,
            'repoName' => $repoName,
            'version' => $version,
            'providerName' => $providerName,
        ];
        saveRepoSettings($settings);

        if (!is_dir($addonDir)) {
            mkdir($addonDir, 0777, true);
        }

        // Handle Icon upload
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $iconPath = $addonDir . 'icon.png';
            move_uploaded_file($_FILES['icon']['tmp_name'], $iconPath);
        } else {
            // echo "Error uploading Icon.<br>";
        }

        // Handle Fanart upload
        if (isset($_FILES['fanart']) && $_FILES['fanart']['error'] === UPLOAD_ERR_OK) {
            $fanartPath = $addonDir . 'fanart.jpg';
            move_uploaded_file($_FILES['fanart']['tmp_name'], $fanartPath);
        } else {
            // echo "Error uploading Fanart.<br>";
        }

        // Generate the XML file
        generateXml($addonId, $repoName, $version, $addonDir, $providerName);
            // Zip the addon directory
        $zipFileName = $addonId . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($addonDir . $zipFileName, ZipArchive::CREATE) === TRUE) { // Change here
            addFolderToZip($addonDir, $zip, strlen($addonDir), $addonId);
            $zip->close();
        } else {
            echo json_encode(['message' => 'Failed to create ZIP file.']); 
        }
    }
}

// Function to generate XML file
function generateXml($addonId, $repoName, $version, $addonDir, $providerName) {
    $currentUrl = rtrim((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], '/index.php');

    $xmlContent = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n" .
        "<addon id=\"$addonId\" name=\"$repoName\" version=\"$version\" provider-name=\"$providerName\">\n" .
        "    <extension point=\"xbmc.addon.repository\" name=\"$repoName\">\n" .
        "        <dir>\n" .
        "            <info compressed=\"false\">$currentUrl/repo/addons.xml</info>\n" .
        "            <checksum>$currentUrl/repo/addons.xml.md5</checksum>\n" .
        "            <datadir zip=\"true\">$currentUrl/repo</datadir>\n" .
        "        </dir>\n" .
        "    </extension>\n" .
        "    <extension point=\"xbmc.addon.metadata\">\n" .
        "        <summary></summary>\n" .
        "        <description></description>\n" .
        "        <disclaimer></disclaimer>\n" .
        "        <platform>all</platform>\n" .
        "        <assets>\n" .
        "            <icon>icon.png</icon>\n" .
        "            <fanart>fanart.jpg</fanart>\n" .
        "        </assets>\n" .
        "    </extension>\n" .
        "</addon>";

    // Save XML file
    $xmlFilePath = $addonDir . 'addon.xml';
    file_put_contents($xmlFilePath, $xmlContent);
}

// Function to load add-ons
function loadAddons() {
    $addonsDir = __DIR__ . '/repo/';
    $addons = [];
    
    $directories = scandir($addonsDir);
    foreach ($directories as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $addonXmlPath = $addonsDir . $dir . '/addon.xml';
        if (file_exists($addonXmlPath)) {
            $xmlContent = simplexml_load_file($addonXmlPath);
            $fanart = file_exists($addonsDir . $dir . "/fanart.jpg") ? "repo/" . $dir . '/fanart.jpg' : "/fanart.png";
            if ($xmlContent) {
                $addons[] = [
                    'id' => (string)$xmlContent['id'],
                    'version' => (string)$xmlContent['version'],
                    'name' => (string)$xmlContent['name'],
                    'fanart' => $fanart,
                    'icon' => file_exists($addonsDir . $dir . '/icon.png') ? "repo/" . $dir . '/icon.png' : ''
                ];
            }
        }
    }
    return $addons;
}

function beautifyAndGenerateMD5($xmlFilePath) {
    // Check if the file exists
    if (!file_exists($xmlFilePath)) {
        throw new Exception("File not found: $xmlFilePath");
    }

    // Load the XML file
    $dom = new DOMDocument();
    $dom->load($xmlFilePath);

    // Set the format output to true
    $dom->formatOutput = true;

    // Save the formatted XML back to the file
    $dom->save($xmlFilePath);

    // Generate MD5 hash of the XML file
    $md5Hash = md5_file($xmlFilePath);

    // Prepare the name for the MD5 file
    $md5FilePath = $xmlFilePath . '.md5';

    // Save the MD5 hash to a new file
    file_put_contents($md5FilePath, $md5Hash);

    // Output the results
    return "MD5 hash generated and saved to: $md5FilePath";
}

// Function to delete an add-on
function deleteAddon($id) {
    $addonDir = __DIR__ . '/repo/' . $id;

    if (is_dir($addonDir)) {
        deleteDirectory($addonDir); // Recursively delete all files and directories
        createAddonsXml(__DIR__ . '/repo/', __DIR__ . '/repo/addons.xml'); // Update addons.xml after deletion
        return true;
    }
    return false;
}

// Helper function to recursively delete a directory
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    // Scan the directory for files and directories
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = "$dir/$file";
        if (is_dir($filePath)) {
            // Recursively delete subdirectory
            deleteDirectory($filePath);
        } else {
            // Delete file
            unlink($filePath);
        }
    }

    // Remove the directory itself
    rmdir($dir);
}


// Handle file uploads and deletion requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET["api"])) {
        $api = $_GET["api"];
        if ($api === "createrepo"){
            handleRepoSetup();
        } else {
            handleUpload();
        }
    }
    else {
        handleUpload();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
    $addonId = $_GET['id'];
    if (deleteAddon($addonId)) {
        echo json_encode(['message' => 'Add-on deleted successfully.']);
    } else {
        echo json_encode(['message' => 'Failed to delete add-on.']);
    }
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if the request is for JSON
    if (isset($_GET['api']) && $_GET['api'] === 'addons') {
        header('Content-Type: application/json');
        echo json_encode(loadAddons());
        exit;
    }
}

function parseAddonMetadata($filePath) {
    // Read the content of the XML file
    $content = file_get_contents($filePath);
    
    if ($content === false) {
        throw new Exception("Error reading file: $filePath");
    }

    // Parse the XML content
    libxml_use_internal_errors(true); // Suppress XML errors
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            echo "XML Error: {$error->message}\n";
        }
        libxml_clear_errors();
        throw new Exception("Error parsing XML from $filePath");
    }

    // Convert SimpleXMLElement to an array
    $addon = json_decode(json_encode($xml), true);

    // Validate the addon structure
    if (!isset($addon['id']) || !isset($addon['version'])) {
        throw new Exception("Invalid addon structure in $filePath: " . json_encode($addon, JSON_PRETTY_PRINT));
    }

    return $addon;
}

function createAddonsXml($repoPath, $outputPath) {
    // Create the root XML element
    $root = new SimpleXMLElement('<addons/>');

    // Create a RecursiveDirectoryIterator to traverse subfolders
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repoPath));

    // Iterate through each file in the directory
    foreach ($iterator as $file) {
        // Check if the file is 'addon.xml'
        if ($file->isFile() && $file->getFilename() === 'addon.xml') {
            // Load the XML file
            $addonXml = simplexml_load_file($file->getPathname());
            if ($addonXml === false) {
                // Handle XML loading error
                continue;
            }

            // Append the whole addon XML to the root element
            $addonCopy = $root->addChild('addon');
            copyXmlContent($addonXml, $addonCopy);
        }
    }

    // Save the XML to a temporary output path
    $tempXmlPath = $outputPath . '.tmp';
    $xmlContent = $root->asXML();
    file_put_contents($tempXmlPath, $xmlContent);

    // Beautify the XML
    beautifyXml($tempXmlPath, $outputPath);
    beautifyAndGenerateMD5($outputPath);
    // Clean up the temporary file
    unlink($tempXmlPath);
}

// Function to beautify and save the XML
function beautifyXml($inputPath, $outputPath) {
    $dom = new DOMDocument();
    $dom->load($inputPath);
    
    // Enable formatting and remove extra whitespace
    $dom->formatOutput = true;

    // Normalize whitespace
    $dom->preserveWhiteSpace = false; // This removes unnecessary whitespace
    $dom->save($outputPath);

    // Optionally, you can remove any remaining unwanted whitespace
    $xmlContent = file_get_contents($outputPath);
    $xmlContent = preg_replace('/>\s+</', '><', $xmlContent); // Remove whitespace between tags
    file_put_contents($outputPath, $xmlContent);
}


// Recursive function to copy the XML content
function copyXmlContent($sourceNode, $targetNode) {
    // Copy all attributes
    foreach ($sourceNode->attributes() as $key => $value) {
        $targetNode->addAttribute($key, (string)$value);
    }

    // Copy child elements recursively
    foreach ($sourceNode->children() as $child) {
        $childCopy = $targetNode->addChild($child->getName(), (string)$child);
        copyXmlContent($child, $childCopy); // Recursively copy child nodes
    }
}



// If no valid request was made, show the main HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kodi Repository Uploader</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to the CSS file -->
</head>
<body>
    <div class="sidebar">
        <h2>Menu</h2>
        <ul>
            <li><a href="#" id="homeLink">Home</a></li>
            <li><a href="#" id="addonsLink">Repository</a></li>
        </ul>
    </div>
    
    <div class="main-content" id="mainContent">
        <h1>Welcome to the Kodi Repository Uploader!</h1>
        <h2>What You Can Do Here</h2>
        <p>This tool allows you to easily upload Kodi add-ons in ZIP format and manage your repository.</p>
        <p>Once uploaded, you can view all your add-ons in the repository.</p>
    </div>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-fanart" id="modalFanart"></div>
            <div class="modal-info">
                <h2 id="addonName"></h2>
                <p><strong>ID:</strong> <span id="addonId"></span></p>
                <p><strong>Version:</strong> <span id="addonVersion"></span></p>
            </div>
            <img id="addonIcon" class="icon" src="" alt="Addon Icon">
            <button id="deleteButton">Delete Add-on</button>
        </div>
    </div>
    <!-- Repo Setup Modal -->
    <div id="repoModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeRepoModal">&times;</span>
            <h2>Repository Setup</h2>
            <p>Configure your repository settings here.</p>
            <form action="?api=createrepo" method="post" enctype="multipart/form-data">
                <label for="addonId">Addon ID:</label>
                <input type="text" id="addonId" name="addonId" required> 

                <label for="repoName">Repository Name:</label>
                <input type="text" id="repoName" name="repoName" required>

                <label for="providerName">Provider Name:</label>
                <input type="text" id="providerName" name="providerName" required>

                <label for="version">Version:</label>
                <input type="text" id="version" name="version" required>

                <input type="file" id="icon" accept="image/*" name="icon" hidden>
                <button type="button" onclick="document.getElementById('icon').click()">Icon</button> 

                <input type="file" id="fanart" accept="image/*" name="fanart" hidden>
                <button type="button" onclick="document.getElementById('fanart').click()">Fanart</button>

                <button type="submit" id="saveSettingsButton">Save Settings</button>
            </form>
        </div>
    </div>


    <div class="footer">
        <p>Created by: <strong>Demonstratorz</strong></p>
    </div>

    <script>
        const mainContent = document.getElementById('mainContent');
        const modal = document.getElementById('myModal');
        const closeModal = document.getElementsByClassName('close')[0];

        // Show the home screen
        function showHomeScreen() {
            mainContent.innerHTML = `
                <h1>Welcome to the Kodi Repository Uploader!</h1>
                <h2>What You Can Do Here</h2>
                <p>This tool allows you to easily upload Kodi add-ons in ZIP format and manage your repository.</p>
                <p>Once uploaded, you can view all your add-ons in the repository.</p>
            `;
        }

        // Show the home screen on load
        showHomeScreen();

        // Show the upload section for add-ons
        document.getElementById('addonsLink').addEventListener('click', () => {
    mainContent.innerHTML = `
        <div class="container"> 
            <div id="uploadSection">
                <div class="upload-area" id="uploadArea">
                    <p>Drag and drop your ZIP file here or click to select</p>
                    <input type="file" name="addonFile" id="fileInput" accept=".zip" required>
                </div>
                <button id="setupButton">Setup Repo</button> 
                <button id="uploadButton">Upload</button>
                <div class="progress" id="uploadProgress">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div id="uploadMessage"></div>
            </div>

            <div id="addonsSection">
                <h2>Uploaded Add-ons</h2>
                <div class="addon-list" id="addonList"></div>
            </div>
        </div> 
    `;
    setUploadEventListeners(); // Set up the upload event listeners
    loadAddons(); // Load existing add-ons

    // Set up the click event for the setup button
    document.getElementById('setupButton').addEventListener('click', openRepoModal);
    });

    // Function to open the repo modal
    function openRepoModal() {
        fetch('/repo_settings.json')
        .then(response => response.json())
        .then(settings => {
            console.log(settings.addonId);
            document.getElementById('addonId').value = settings.addonId || '';
            document.getElementById('repoName').value = settings.repoName || '';
            document.getElementById('version').value = settings.version || '';
            document.getElementById('providerName').value = settings.providerName || '';
        });
        document.getElementById('repoModal').style.display = 'block';
    }

    // Close the repo modal
    document.getElementById('closeRepoModal').onclick = () => {
        document.getElementById('repoModal').style.display = 'none';
    };

    // document.getElementById('saveSettingsButton').onclick = () => {
    //     const addonId = document.getElementById('addonId').value;
    //     const repoName = document.getElementById('repoName').value;
    //     const version = document.getElementById('version').value;
    //     const providerName = document.getElementById('providerName').value;
    //     const iconFile = document.getElementById('iconUpload').files[0];
    //     const fanartFile = document.getElementById('fanartUpload').files[0];

    //     const formData = new FormData();
    //     formData.append('addonId', addonId);
    //     formData.append('repoName', repoName);
    //     formData.append('version', version);
    //     formData.append('providerName', providerName);
    //     if (iconFile) {
    //         formData.append('iconFile', iconFile);
    //     }
    //     if (fanartFile) {
    //         formData.append('fanartFile', fanartFile);
    //     }

    //     // Send formData to the server
    //     fetch('index.php?api=createrepo', {
    //         method: 'POST',
    //         body: formData,
    //     })
    //     .then(response => response.json())
    //     .then(data => {
    //         // Handle the response from the server
    //         alert(data.message); // Display message from server
    //         document.getElementById('repoModal').style.display = 'none'; // Close modal
    //     })
    //     .catch(error => {
    //         console.error('Error:', error);
    //     });
    // };

    // Add event listener for the Home link
    document.getElementById('homeLink').addEventListener('click', (event) => {
        event.preventDefault(); // Prevent default link behavior
        showHomeScreen(); // Load home content
    });

        // Function to set upload event listeners
        function setUploadEventListeners() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const uploadButton = document.getElementById('uploadButton');
            const progressBar = document.getElementById('progressBar');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadMessage = document.getElementById('uploadMessage');

            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });

            uploadArea.addEventListener('dragover', (event) => {
                event.preventDefault();
                uploadArea.classList.add('drag-over');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });

            uploadArea.addEventListener('drop', (event) => {
                event.preventDefault();
                uploadArea.classList.remove('drag-over');
                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files; // Set the dropped files to the file input
                    uploadButton.click(); // Automatically trigger the upload button click
                }
            });

            uploadButton.addEventListener('click', () => {
                if (fileInput.files.length > 0) {
                    const formData = new FormData();
                    formData.append('addonFile', fileInput.files[0]);

                    // Show progress bar
                    uploadProgress.style.display = 'block';
                    progressBar.style.width = '0%';
                    uploadMessage.textContent = '';

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'index.php', true); // POST to the same page

                    // Update progress bar
                    xhr.upload.onprogress = (event) => {
                        if (event.lengthComputable) {
                            const percentComplete = (event.loaded / event.total) * 100;
                            progressBar.style.width = percentComplete + '%';
                        }
                    };

                    // Handle response
                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            console.log(xhr.responseText);
                            const response = JSON.parse(xhr.responseText);
                            uploadMessage.textContent = response.message;
                            loadAddons(); // Refresh the addons list after upload
                        } else {
                            uploadMessage.textContent = 'Upload failed: ' + xhr.responseText;
                        }
                        uploadProgress.style.display = 'none'; // Hide progress bar
                    };

                    xhr.send(formData);
                } else {
                    alert('Please select a ZIP file to upload.');
                }
            });
        }

        // Load add-ons
        function loadAddons() {
            const addonList = document.getElementById('addonList');
            addonList.innerHTML = ''; // Clear existing items

            fetch('?api=addons', { method: 'GET' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    data.forEach(addon => {
                        const addonItem = document.createElement('div');
                        addonItem.classList.add('addon-item');
                        addonItem.innerHTML = `<img src="${addon.icon}" alt="${addon.name}"><br>${addon.name}`;
                        addonItem.addEventListener('click', () => openAddonModal(addon));
                        addonList.appendChild(addonItem);
                    });
                })
                .catch(error => {
                    console.error('Error fetching add-ons:', error);
                });
        }

        // Open addon modal
        function openAddonModal(addon) {
            document.getElementById('modalFanart').style.backgroundImage = `url('${addon.fanart}')`;
            document.getElementById('addonName').textContent = addon.name;
            document.getElementById('addonId').textContent = addon.id;
            document.getElementById('addonVersion').textContent = addon.version;
            document.getElementById('addonIcon').src = addon.icon;

            const deleteButton = document.getElementById('deleteButton');
            deleteButton.onclick = () => deleteAddon(addon.id);

            modal.style.display = 'block';
        }

        // Delete add-on
        function deleteAddon(addonId) {
            fetch(`?id=${addonId}`, { method: 'DELETE' })
                .then(response => {
                    if (response.ok) {
                        modal.style.display = 'none';
                        loadAddons(); // Refresh the addons list
                    } else {
                        alert('Failed to delete add-on.');
                    }
                });
        }

        // Close the modal
        closeModal.onclick = () => {
            modal.style.display = 'none';
        };
    </script>
</body>
</html>
