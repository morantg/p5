<?php
require('../vendor/autoload.php');
require 'model/autoload.php';

function register()
{
	$twig = App::get_twig();
	$errors = array();
	$db = DBFactory::getMysqlConnexionWithPDO();
	$user = $db ->query('SELECT * from users ')->fetchAll();
	if(!empty($_POST)){
		//$errors = array();
		$validator = new validator($_POST);
		$validator->isAlpha('username',"Votre pseudo n'est pas valide (alphanumérique)");
	
	if($validator->isValid()){
		$validator->isUniq('username', $db,'users','Ce pseudo est pris');
	}
	$validator->isEmail('email',"email non valide");
	
	if($validator->isValid()){
		$validator->isUniq('email', $db,'users','Ce mail est déjà pris');
	}
	$validator->isConfirmed('password','vous devez rentrer un mdp valide');

	if($validator->isValid()){
		App::getAuth()->register($db,$_POST['username'],$_POST['password'],$_POST['email']);
		Session::getInstance()->setFlash('success','email de confirmation envoyé');
		App::redirect('index.php?action=login');
	}else{
		$errors = $validator->getErrors(); 
	}
}
	echo $twig->render('register_view.php',array(
		'errors' => $errors
	));
}

function login()
{
	$twig = App::get_twig();
	$auth = App::getAuth();
	$db = DBFactory::getMysqlConnexionWithPDO();
	$auth->connectFromCookie($db);
	$session = Session::getInstance();
	
	if($auth->user()){
		App::redirect('index.php?action=account');
	}
	if(!empty($_POST) && !empty($_POST['username']) && !empty($_POST['password'])){
		$user = $auth->login($db,$_POST['username'],$_POST['password'],isset($_POST['remember']));
		//$session = Session::getInstance();
		if ($user){
			$session->setFlash('success','Vous êtes maintenant connecté');
			App::redirect('index.php?action=account');
		}else{
			$session->setFlash('danger','Identifiant ou mot de passe incorrecte');
		}
	}
	echo $twig->render('login_view.php',array(
			'session_instance' => $session
	));
}

function logout()
{
	App::getAuth()->logout();
	Session::getInstance()->setFlash('success','vous etes deco');
	App::redirect('index.php?action=login');
}

function confirm()
{
	$db = DBFactory::getMysqlConnexionWithPDO();
	if(App::getAuth()->confirm($db, $_GET['id'], $_GET['token'], Session::getInstance())){
		Session::getInstance()->setFlash('success',"compte validé");
		App::redirect('index.php?action=account');
	}else{
		Session::getInstance()->setFlash('danger',"ce token n'est plus valide");
		App::redirect('index.php?action=login');
	}	
}

function account()
{
	
	$twig = App::get_twig();
	App::getAuth()->restrict();
	$session_instance = Session::getInstance();
	if(!empty($_POST)){
		if(empty($_POST['password']) || $_POST['password'] != $_POST['password_confirm']){
			$_SESSION['flash']['danger'] = "les mdp ne corresponde pas";
	}else{
		$user_id = $_SESSION['auth']->id;
		$password= password_hash($_POST['password'],PASSWORD_BCRYPT);
		$db = DBFactory::getMysqlConnexionWithPDO();
		$req = $db->prepare('UPDATE users SET password = ?');
		$req->execute([$password]);
		$_SESSION['flash']['success'] = "mdp mis a jour";

		App::redirect('index.php?action=account');
	    }
	}
	echo $twig->render('account_view.php',array(
		'session' => $_SESSION,
		'session_instance' => $session_instance 
	));
}

function forget()
{
	$twig = App::get_twig();
	$session = Session::getInstance();
	if(!empty($_POST) && !empty($_POST['email'])){
	$db = DBFactory::getMysqlConnexionWithPDO();	
	$auth = App::getAuth();
	//$session = Session::getInstance();
		if($auth->resetPassword($db,$_POST['email'])){
			$session->setFlash('success','les instructions du rappel de mot de passe vous ont été envoyées par emails');
			App::redirect('index.php?action=login');
		}else{
			$session->setFlash('danger','pas de correspondance');
			App::redirect('index.php?action=forget');
		}
	}
	echo $twig->render('forget_view.php',array(
			'session_instance' => $session

	)); 
}

function reset_password()
{
	$twig = App::get_twig();
	if(isset($_GET['id']) && isset($_GET['token'])){
	$auth = App::getAuth();
	$db = DBFactory::getMysqlConnexionWithPDO();
	$user = $auth->checkResetToken($db,$_GET['id'],$_GET['token']);

		if($user){
			if(!empty($_POST)){
				$validator = new Validator($_POST);
				$validator->isConfirmed('password');
				if($validator->isValid()){
					$password = $auth->hashPassword($_POST['password']);
					$req = $db->prepare('UPDATE users SET password = ?, reset_at = NULL, reset_token = NULL WHERE id = ?');
					$req->execute([$password,$_GET['id']]);
					$auth->connect($user);
					Session::getInstance()->setFlash('success',"Votre mot de passe a bien été modifié");
					App::redirect('index.php?action=account');
				}
			}
			}else{
				Session::getInstance()->setFlash('danger',"ce token n'est plus valide");
				App::redirect('index.php?action=login');
		}
		}else{
			App::redirect('index.php?action=login');
	}
	echo $twig->render('reset_view.php'); 
}

function listPosts()
{
	$twig = App::get_twig();
	$auth = App::getAuth();
    $db = DBFactory::getMysqlConnexionWithPDO();
    $postManager = new NewsManager($db); 
    $posts = $postManager->getPosts();  

    echo $twig->render('listPostsView.php',array(
		'session' => $_SESSION,
		'post'    => $posts
	));
}

function post()
{
	$twig = App::get_twig();
    $auth = App::getAuth();
    $db = DBFactory::getMysqlConnexionWithPDO();
    $postManager = new NewsManager($db);
    $commentManager = new CommentManager($db);

    $post = $postManager->getPost($_GET['id']);
    $comments = $commentManager->getComments($_GET['id']);

    echo $twig->render('PostView.php',array(
		'session' => $_SESSION,
		'post'    => $post,
		'comments'=> $comments
	));
}

function addComment($postId, $author, $comment)
{
    $db = DBFactory::getMysqlConnexionWithPDO();
    $commentManager = new CommentManager($db);

    $affectedLines = $commentManager->postComment($postId, $author, $comment);

    if ($affectedLines === false) {
        throw new Exception('Impossible d\'ajouter le commentaire !');
    }
    else {
        header('Location: index.php?action=post&id=' . $postId);
    }

    require('view/PostView.php');
}


function edition()
{
	$db = DBFactory::getMysqlConnexionWithPDO();
	App::getAuth()->restrict_admin($db);
	$twig = App::get_twig();
	$news = null;
	$message = '';
	
	$manager = new NewsManager($db);

	if (isset($_GET['modifier']))
	{
  	$news = $manager->getUnique((int) $_GET['modifier']);
	}

	if (isset($_GET['supprimer']))
	{
  	$manager->delete((int) $_GET['supprimer']);
  	$message = 'La news a bien été supprimée !';
	}

	if (isset($_POST['auteur']))
	{
  	$news = new News(
    	[
      	'auteur' => $_POST['auteur'],
      	'titre' => $_POST['titre'],
      	'contenu' => $_POST['contenu']
    	]
  	);
  	
  
  	if (isset($_POST['id']))
  	{
   	 $news->setId($_POST['id']);
  	}
  
  	if ($news->isValid())
  	{
   	 $manager->save($news);
    
    	$message = $news->isNew() ? 'La news a bien été ajoutée !' : 'La news a bien été modifiée !';
 	}
  	else
  	{
    	$erreurs = $news->erreurs();
  	}
  }
	echo $twig->render('admin_view.php',array(
		'session' => $_SESSION,
		'new'    => $news,
		'message' => $message,
		'manager' => $manager
		
		
	));
}




