<?php
namespace controller;

class PostController extends Controller
{

    private $mysql_db;
    private $postManager;
    private $commentManager;
    private $auth;
    private $session;

    public function __construct($mysql_db, $postManager, $commentManager, $auth, $session)
    {
        $this->mysql_db = $mysql_db;
        $this->postManager = $postManager;
        $this->commentManager = $commentManager;
        $this->auth = $auth;
        $this->session = $session;
    }

    public function list()
    {
        $posts = $this->postManager->getList();
        $session_user = $_SESSION;
        
        $this->render('listView.php', array(
            'session' => $session_user,
            'post'    => $posts,
            'session_instance' => $this->session
        ));
    }

    public function show()
    {
        $id = filter_input(INPUT_GET, 'id');
        $post = $this->postManager->getUnique($id);
        if (!$post) {
            \model\App::redirect('NotFound');
        }
        $comments = $this->commentManager->getComments($id);
        $session_user = $_SESSION;

        $this->render('PostView.php', array(
        'session' => $session_user,
        'post'    => $post,
        'comments'=> $comments,
        'session_instance' => $this->session
        ));
    }

    public function edit()
    {
        $news = null;
        $erreurs = null;
        $this->auth->restrict();
        $this->auth->restrictAdmin($this->mysql_db);
        
        $users = $this->auth->users($this->mysql_db);
        $comments = $this->commentManager->allCommentsUnpublished();
        
        $modifier = filter_input(INPUT_GET, 'modifier');
        $supprimer = filter_input(INPUT_GET, 'supprimer');
        $permission = filter_input(INPUT_POST, 'permission');
        $auteur = filter_input(INPUT_POST, 'auteur');
        $id = filter_input(INPUT_POST, 'id');
        
        if ($modifier) {
            $news = $this->postManager->getUnique($modifier);
        }
        if ($supprimer) {
            $this->postManager->delete($supprimer);
            $this->session->setFlash('success', 'La news a bien été supprimée !');
            \model\App::redirect('Edition');
        }
        if ($permission) {
            $id = filter_input(INPUT_POST, 'id');
            $this->auth->changer_permission($this->mysql_db, $permission, $id);
            $this->session->setFlash('success', 'La nouvelle permission a bien été adoptée !');
            \model\App::redirect('Edition');
        }
        if (isset($_POST['ids'])) {
            $ids = $_POST['ids'];
            $this->commentManager->publication($ids);
            $this->session->setFlash('success', 'les commentaires ont bien été publiés !');
            \model\App::redirect('Edition');
        }
        if (isset($_POST['delete_ids'])) {
            $delete_ids = $_POST['delete_ids'];
            $this->commentManager->delete_comment($delete_ids);
            $this->session->setFlash('success', 'les commentaires ont bien été supprimés !');
            \model\App::redirect('Edition');
        }
        if ($auteur) {
            $titre = filter_input(INPUT_POST, 'titre');
            $contenu = filter_input(INPUT_POST, 'contenu');
            $news = new \model\News(
                [
                'auteur' => $auteur,
                'titre' => $titre,
                'contenu' => $contenu
                ]
            );
            
            if ($id) {
                $news->setId($id);
            }
      
            if ($news->isValid()) {
                $this->postManager->save($news);
                if ($news->isNew()) {
                    $this->session->setFlash('success', 'La news a bien été ajoutée !');
                } else {
                    $this->session->setFlash('success', 'La news a bien été modifiée !');
                }
                \model\App::redirect('Edition');
            } else {
                $erreurs = $news->erreurs();
            }
        }
        $this->render('adminView.php', array(
            'session' => $_SESSION,
            'new'    => $news,
            'manager' => $this->postManager,
            'session_instance' => $this->session,
            'erreurs' => $erreurs,
            'users' => $users,
            'comments' => $comments
        ));
    }

    public function addComment()
    {
        $news_id = filter_input(INPUT_GET, 'id');
        if (empty($_POST['comment'])) {
            $this->session->setFlash('danger', 'commentaire vide');
            \model\App::redirect('/News/' . $news_id);
        }
        $author = filter_input(INPUT_POST, 'author');
        $comment = filter_input(INPUT_POST, 'comment');
        $affectedLines = $this->commentManager->postComment($news_id, $author, $comment);
        if ($affectedLines === false) {
            throw new Exception('Impossible d\'ajouter le commentaire !');
        } else {
            $this->session->setFlash('success', 'votre message a été soumis a la publication');
            \model\App::redirect('/News/' . $news_id);
        }
        $this->render('PostView.php', array('session' => $_SESSION));
    }
}
