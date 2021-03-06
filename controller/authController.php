<?php
namespace controller;

class AuthController extends Controller
{

    private $mysql_db;
    private $auth;
    private $session;

    public function __construct($mysql_db, $auth, $session)
    {
        $this->mysql_db = $mysql_db;
        $this->auth = $auth;
        $this->session = $session;
    }

    public function login()
    {
        $this->auth->connectFromCookie($this->mysql_db);
        
        if ($this->auth->user()) {
            \model\App::redirect('MonCompte');
        }
        $username = filter_input(INPUT_POST, 'username');
        $password = filter_input(INPUT_POST, 'password');
        $remember = filter_input(INPUT_POST, 'remember');
        
        if (!empty($_POST) && $username && $password) {
            $user = $this->auth->login($this->mysql_db, $username, $password, isset($remember));
            if ($user) {
                $this->session->setFlash('success', 'Vous êtes maintenant connecté');
                \model\App::redirect('MonCompte');
            } else {
                $this->session->setFlash('danger', 'Identifiant ou mot de passe incorrecte');
            }
        }
        $this->render('loginView.php', array(
            'session_instance' => $this->session
        ));
    }

    public function account()
    {
        $this->auth->connectFromCookie($this->mysql_db);
        $this->auth->restrict();
        $password = filter_input(INPUT_POST, 'password');
        $password_confirm = filter_input(INPUT_POST, 'password_confirm');
        
        if (!empty($_POST)) {
            if (empty($password) || $password != $password_confirm) {
                $this->session->setFlash('danger', 'les mots de passe ne corresponde pas');
            } else {
                $user_id = $_SESSION['auth']->id;
                $password_hash= password_hash($password, PASSWORD_BCRYPT);
                $this->auth->passwordUpdate($password_hash, $this->mysql_db, $user_id);
                $this->session->setFlash('success', 'mot de passe mis a jour');
                \model\App::redirect('MonCompte');
            }
        }
        $this->render('accountView.php', array(
            'session' => $_SESSION,
            'session_instance' => $this->session
        ));
    }

    public function logout()
    {
        $this->auth->logout();
        $this->session->setFlash('success', 'vous êtes déconnecté');
        \model\App::redirect('Connection');
    }

    public function register()
    {
        $errors = array();
        if (!empty($_POST)) {
            $validator = new \model\validator($_POST);
            $validator->isAlpha('username', "Votre pseudo n'est pas valide (alphanumérique)");
            
            if ($validator->isValid()) {
                $validator->isUniq('username', $this->mysql_db, 'users', 'Ce pseudo est déja pris');
            }
            $validator->isEmail('email', "email non valide");
            
            if ($validator->isValid()) {
                $validator->isUniq('email', $this->mysql_db, 'users', 'Ce mail est déjà pris');
            }
            $validator->isConfirmed('password', 'vous devez rentrer un mot de passe valide');
            
            if ($validator->isValid()) {
                $this->auth->register($this->mysql_db, $_POST['username'], $_POST['password'], $_POST['email']);
                $this->session->setFlash('success', 'email de confirmation envoyé');
                \model\App::redirect('Connection');
            } else {
                $errors = $validator->getErrors();
            }
        }
        $this->render('registerView.php', array('errors' => $errors));
    }

    public function confirm()
    {
        $id = filter_input(INPUT_GET, 'id');
        $token = filter_input(INPUT_GET, 'token');
        if ($this->auth->confirm($this->mysql_db, $id, $token, $this->session)) {
            $this->session->setFlash('success', "compte validé");
            \model\App::redirect('MonCompte');
        } else {
            $this->session->setFlash('danger', "ce token n'est plus valide");
            \model\App::redirect('Connection');
        }
    }

    public function forget()
    {
        $email = filter_input(INPUT_POST, 'email');
        if ($email) {
            if ($this->auth->resetPassword($this->mysql_db, $email)) {
                $this->session->setFlash(
                    'success',
                    'les instructions du rappel de mot de passe vous ont été envoyées par emails'
                );
                \model\App::redirect('Connection');
            } else {
                $this->session->setFlash('danger', 'pas de correspondance');
                \model\App::redirect('Forget');
            }
        }
        $this->render('forgetView.php', array('session_instance' => $this->session));
    }

    public function resetPassword()
    {
        $user_id = filter_input(INPUT_GET, 'id');
        $token = filter_input(INPUT_GET, 'token');
        if ($user_id && $token) {
            $user = $this->auth->checkResetToken($this->mysql_db, $user_id, $token);
            if ($user) {
                if (!empty($_POST)) {
                    $validator = new \model\Validator($_POST);
                    $validator->isConfirmed('password');
                    if ($validator->isValid()) {
                        $password = $this->auth->hashPassword($_POST['password']);
                        $this->auth->confirmReset($password, $user_id, $this->mysql_db);
                        $this->auth->connect($user);
                        $this->session->setFlash('success', "Votre mot de passe a bien été modifié");
                        \model\App::redirect('MonCompte');
                    }
                }
            } else {
                    $this->session->setFlash('danger', "ce token n'est plus valide");
                    \model\App::redirect('Connection');
            }
        } else {
                \model\App::redirect('Connection');
        }
        $this->render('resetView.php');
    }
}
