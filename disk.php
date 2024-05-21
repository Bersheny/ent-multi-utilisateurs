<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'ent_multi_utilisateurs';
$port = '3308';

$_root = 'C:/wamp64/www/ent-multi_utilisateurs/users/';

$connexion = new mysqli($host, $username, $password, $database, $port);

if ($connexion->connect_error) {
    die("Connexion échouée : " . $connexion->connect_error);
}

function isUsernameUnique($username)
{
    global $connexion;

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $connexion->query($sql);

    return ($result->num_rows === 0);
}

function registerUser($username, $password)
{
    global $connexion;

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // SQL query to insert user into the database
    $sql = "INSERT INTO users (username, password) VALUES ('$username', '$hashedPassword')";

    if ($connexion->query($sql) === TRUE) {
        // Create a directory for the new user
        $userDirectory = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $username;

        if (!file_exists($userDirectory)) {
            mkdir($userDirectory);

            // Respond with success message
            sendResponse('REGISTER', 'Enregistrement réussi', null);
        } else {
            // If directory creation fails, delete the user record from the database
            $deleteUserSql = "DELETE FROM users WHERE username='$username'";
            $connexion->query($deleteUserSql);

            sendResponse('REGISTER', null, 'Erreur lors de la création du répertoire utilisateur');
        }
    } else {
        // If database insertion fails, respond with an error message
        sendResponse('REGISTER', null, 'Erreur lors de l\'enregistrement');
    }
}

function loginUser($username, $password)
{
    global $connexion;

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $connexion->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            return true;
        }
    }

    return false;
}

function sendResponse($cmd, $content = null, $error = null)
{
    $response = array(
        'cmd' => $cmd,
        'content' => $content,
        'error' => $error,
        'debug' => count($_SESSION)
    );
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

if (isset($_GET['CMD'])) {
    $cmd = strtoupper($_GET['CMD']);
    switch ($cmd) {
        case 'REGISTER':
            if (isset($_GET['PARAM1']) && isset($_GET['PARAM2'])) {
                $username = $_GET['PARAM1'];
                $password = $_GET['PARAM2'];

                if (isUsernameUnique($username)) {
                    if (registerUser($username, $password)) {
                        $_SESSION["username"] = $username;
                        sendResponse('REGISTER', 'Enregistrement réussi', null);
                    } else {
                        sendResponse('REGISTER', null, 'Erreur lors de l\'enregistrement');
                    }
                } else {
                    sendResponse('REGISTER', null, 'Le nom d\'utilisateur existe déjà');
                }
            } else {
                sendResponse('REGISTER', null, 'Pas assez de paramètres.');
            }
            break;
        case 'LOGIN':
            if (isset($_GET['PARAM1']) && isset($_GET['PARAM2'])) {
                $username = $_GET['PARAM1'];
                $password = $_GET['PARAM2'];

                if (loginUser($username, $password)) {
                    $_SESSION["username"] = $username;
                    $_SESSION["pwd"] = "/";
                    $_SESSION["home"] = $_root . $_SESSION["username"];

                    sendResponse('LOGIN', 'Connexion réussie', $_SESSION["username"]);
                } else {
                    sendResponse('LOGIN', null, 'Identifiants incorrects');
                }
            } else {
                sendResponse('LOGIN', null, 'Pas assez de paramètres.');
            }
            break;
        case 'LOGOUT':
            if (isset($_SESSION["username"])) {
                session_destroy();
                sendResponse('LOGOUT', 'Déconnexion réussie', null);
            } else {
                sendResponse('LOGOUT', null, 'Aucun utilisateur n\'est connecté');
            }
            break;
        case 'WHOAMI':
            if (count($_SESSION)) {
                sendResponse('WHOAMI', $_SESSION["username"], null);
            } else {
                sendResponse('WHOAMI', null, 'Aucun utilisateur n\'est connecté');
            }
            break;
        // Modify the 'DIR' case to list contents similar to "dir" command
        case 'DIR':
            if (count($_SESSION) && isset($_SESSION['pwd'])) {
                $currentDir = $_root . $_SESSION["username"] . $_SESSION['pwd'];

                if (is_dir($currentDir)) {
                    $dirContent = scandir($currentDir);
                    $dirContent = array_diff($dirContent, array('..', '.'));

                    // Display the directory content as an associative array with types (file or directory)
                    $formattedContent = array();
                    foreach ($dirContent as $item) {
                        $itemPath = $currentDir . '/' . $item;
                        $formattedContent[$item] = is_dir($itemPath) ? 'Directory' : 'File';
                    }

                    sendResponse('DIR', $formattedContent, $_SESSION['pwd']);
                } else {
                    sendResponse('DIR', null, 'Current directory does not exist');
                }
            } else {
                sendResponse('DIR', null, 'No user is connected');
            }
            break;
        case 'CD':
            if (count($_SESSION) && isset($_SESSION['pwd'])) {
                $currentDir = $_root . $_SESSION["username"] . $_SESSION['pwd'];

                if (is_dir($currentDir)) {
                    $dirContent = scandir($currentDir);
                    $dirContent = array_diff($dirContent, array('..', '.'));

                    sendResponse('CD', $dirContent, $_SESSION['pwd']);
                } else {
                    sendResponse('CD', null, 'Current directory does not exist');
                }
            } else {
                sendResponse('CD', null, 'No user is connected');
            }
            break;
        case 'HOME':
            if (count($_SESSION)) {
                sendResponse('HOME', $_SESSION["home"], null);
            } else {
                sendResponse('HOME', null, 'Aucun utilisateur n\'est connecté');
            }
            break;
        case 'CREATEFILE':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1']) && isset($_GET['PARAM2'])) {
                $filePath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                if (!empty($_GET['PARAM1']) && !file_exists($filePath)) {
                    file_put_contents($filePath, $_GET['PARAM2']);
                    sendResponse('CREATEFILE', 'Fichier créé avec succès', null);
                } else {
                    sendResponse('CREATEFILE', null, 'Le fichier existe déjà ou des paramètres sont manquants');
                }
            } else {
                sendResponse('CREATEFILE', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        case 'WRITEFILE':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1']) && isset($_GET['PARAM2'])) {
                $filePath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                // Écrire le contenu dans le fichier
                if (!empty($_GET['PARAM1']) && !empty($_GET['PARAM2'])) {
                    file_put_contents($filePath, $_GET['PARAM2']);
                    sendResponse('WRITEFILE', 'Contenu du fichier mis à jour avec succès', null);
                } else {
                    sendResponse('WRITEFILE', null, 'des paramètres sont manquants');
                }
            } else {
                sendResponse('WRITEFILE', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        case 'READFILE':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1'])) {
                $filePath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                if (!empty($_GET['PARAM1']) && file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    sendResponse('READFILE', $fileContent, null);
                } else {
                    sendResponse('READFILE', null, 'Le fichier n\'existe pas ou un paramètre est manquant');
                }
            } else {
                sendResponse('READFILE', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        case 'DELETEFILE':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1'])) {
                $filePath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                if (!empty($_GET['PARAM1']) && file_exists($filePath)) {
                    // Supprimer le fichier
                    unlink($filePath);
                    sendResponse('DELETEFILE', 'Fichier supprimé avec succès', null);
                } else {
                    sendResponse('DELETEFILE', null, 'Le fichier n\'existe pas ou un paramètre est manquant');
                }
            } else {
                sendResponse('DELETEFILE', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        case 'MKDIR':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1'])) {
                $dirPath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                if (!empty($_GET['PARAM1']) && !file_exists($dirPath)) {
                    // Créer le répertoire
                    mkdir($dirPath);
                    sendResponse('MKDIR', 'Répertoire créé avec succès', null);
                } else {
                    sendResponse('MKDIR', null, 'Le répertoire existe déjà ou un paramètre est manquant');
                }
            } else {
                sendResponse('MKDIR', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        case 'RMDIR':
            if (isset($_SESSION["username"]) && isset($_GET['PARAM1'])) {
                $dirPath = 'C:/wamp64/www/ent-multi_utilisateurs/users/' . $_SESSION["username"] . '/' . $_GET['PARAM1'];
                if (!empty($_GET['PARAM1']) && file_exists($dirPath) && is_dir($dirPath)) {
                    // Supprimer le répertoire
                    rmdir($dirPath);
                    sendResponse('RMDIR', 'Répertoire supprimé avec succès', null);
                } else {
                    sendResponse('RMDIR', null, 'Le répertoire n\'existe pas ou un paramètre est manquant');
                }
            } else {
                sendResponse('RMDIR', null, 'Aucun utilisateur n\'est connecté ou pas assez de paramètres');
            }
            break;
        default:
            sendResponse($cmd, null, 'Commande inconnue : ' . $cmd);
    }
} else {
    sendResponse(null, null, 'Pas assez de paramètres.');
}
?>
