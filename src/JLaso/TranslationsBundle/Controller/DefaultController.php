<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Key;
use JLaso\TranslationsBundle\Entity\Message;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\KeyRepository;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\Repository\MessageRepository;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\NewProjectType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ODM\MongoDB\DocumentManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;

/**
 * Class DefaultController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/")
 */
class DefaultController extends Controller
{

    const APPROVE    = TranslationLog::APPROVE;
    const DISAPPROVE = TranslationLog::DISAPPROVE;

    /** @var  EntityManager */
    protected $em;
    /** @var  DocumentManager */
    protected $dm;
    protected $config;
    /** @var  TranslationsManager */
    protected $translationsManager;
    /** @var User */
    protected $user;
    /** @var  Translator */
    protected $translator;
    /** @var  RestService */
    protected $restService;

    protected function init()
    {
        $this->em                  = $this->container->get('doctrine.orm.default_entity_manager');
        $this->config              = $this->container->getParameter('jlaso_translations');
        $this->translationsManager = $this->container->get('jlaso.translations_manager');
        $this->user                = $this->get('security.context')->getToken()->getUser();
        $this->translator          = $this->container->get('translator');
        $this->restService         = $this->container->get('jlaso.rest_service');
        /** @var DocumentManager $dm */
        $this->dm                  = $this->container->get('doctrine.odm.mongodb.document_manager');
    }

    /**
     * @Route("/", name="home")
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('user_login'));
    }

    /**
     * @Route("/translations", name="user_index")
     * @Template()
     */
    public function userIndexAction()
    {
        $this->init();

        $projects = $this->translationsManager->getProjectsForUser($this->user);

        return array(
            'projects' => $projects,
        );
    }

    /**
     * Create new Project
     *
     * @Route("/new-project", name="new_project")
     * @Template()
     */
    public function newProjectAction(Request $request)
    {
        $this->init();

        $project  = new Project();
        $form = $this->createForm(new NewProjectType(), $project);

        if($request->isMethod('POST')){
            $form->bind($request);

            if ($form->isValid()) {

                $permission = new Permission();
                $permission->setUser($this->user);
                $permission->setProject($project);
                $permission->addPermission(Permission::OWNER);
                // Give permission to write in all languages
                $permission->addPermission(Permission::WRITE_PERM, '*');
                $this->em->persist($permission);
                $this->em->persist($project);
                $this->em->flush();

                /** @var MailerService $mailer */
                $mailer = $this->get('jlaso.mailer_service');
                try{
                    $send   = $mailer->sendNewProjectMessage($project, $this->user);
                }catch(\Exception $e){
                    if($this->get('kernel')->getEnvironment()=='prod'){
                         // ?
                    }
                }

                return $this->redirect($this->generateUrl('user_index'));
            }

        }

        return array(
            'error'         => null,
            'form'          => $form->createView(),
            'project'       => $project,
        );
    }

    /**
     * @Route("/translations/{projectId}/{catalog}", name="translations")
     * @Template()
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function translationsAction(Project $project, $catalog ='')
    {
        $this->init();
        //$permission = $this->translationsManager->userHasProject($this->user, $project);
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);

        if(!$permission instanceof Permission){
            throw new AclException($this->translator->trans('error.acl.not_enough_permissions_to_manage_this_project'));
        }

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->dm->getRepository('TranslationsBundle:Translation');

//        /** @var ArrayCollection $localKeys */
//        $localKeys = $project->getKeys();
//        if((!$bundle || !$catalog) && count($localKeys)){
//            $bundle  = $localKeys->first()->getBundle();
//            $catalog = $localKeys->first()->getCatalog();
//            //ldd($bundle, $catalog);
//            return $this->redirect($this->generateUrl('translations', array(
//                        'projectId'  => $project->getId(),
//                        'bundle'     => $bundle,
//                        'catalog'    => $catalog,
//                        'currentKey' => $currentKey,
//                    )
//                )
//            );
//        }
        //$keyRepository = $this->getKeyRepository();
        //$bundles       = $keyRepository->findAllBundlesForProject($project);
        //$keys          = $keyRepository->findAllKeysForProjectBundleAndCatalog($project, $bundle, $catalog);
        $keys = $translationRepository->getKeys($project->getId(), $catalog);
        $keysAssoc = array();
        foreach($keys as $key){
            $keysAssoc = $this->translationsManager->iniToAssoc($key['key'], $keysAssoc);
        }


        $managedLocales = explode(',',$project->getManagedLocales());
        $transData = array();
        /*
        foreach($keys as $key){
            $data = array(
                'id'       => $key['id'],
                'key'      => $key['key'],
                'id_html'  => $this->translationsManager->keyToHtmlId($key['key']),
                'comment'  => $key->getComment(),
                'bundle'   => $key->getBundle(),
                'messages' => array(),
                'info'     => array(),
            );
            foreach($key->getMessages() as $message){
                $data['messages'][$message->getLanguage()] = $message->getMessage();
                $data['info'][$message->getLanguage()] = array(
                    'approved' => $message->getApproved(),
                    'id'       => $message->getId(),
                );
            }
            $transData[] = $data;
        }
        */

        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);
        $projects  = $this->translationsManager->getProjectsForUser($this->user);
        $catalogs  = $translationRepository->getCatalogs($project->getId());

        return array(
            'projects'          => $projects,
            'project'           => $project,
            'catalogs'          => $catalogs,
            'keys'              => $keysAssoc,
            ////'keys_raw'        => $keys,
            'current_catalog'   => $catalog,
            'managed_languages' => $managedLocales,
            'trans_data'        => $transData,
            //'current_key'       => $currentKey,
            'languages'         => $languages,
            'permissions'       => $permission->getPermissions(),
        );
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function printResult($data)
    {
        header('Content-type: application/json');
        print json_encode($data);
        exit;
    }

    /**
     * @Route("/translations-messages", name="translations_messages")
     * @ Method("POST")
     */
    public function getMessages(Request $request)
    {
        $this->init();
        $projectId = $request->get('projectId');
        $catalog   = $request->get('catalog');
        $key       = $request->get('key');
        /** @var Project $project */
        $project   = $this->getProjectRepository()->find($projectId);

        if(!$project){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'project '.$projectId.' not exists',
                )
            );
        };

        $managedLocales = explode(',',$project->getManagedLocales());
        /** @var DocumentManager $dm */
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');
        /** @var TranslationRepository $translationRepository */
        $translationRepository = $dm->getRepository('TranslationsBundle:Translation');
        /** @var Translation $document */
        $translation = $translationRepository->getMessagesDocument($projectId, $catalog, $key);
        $permission = $this->translationsManager->getPermissionForUserAndProject($this->user, $project);
        $languages = $this->getLanguageRepository()->findAllLanguageIn($managedLocales, true);

        $html = $this->renderView("TranslationsBundle:Default:messages.html.twig",array(
                'translation'     => $translation,
                'managed_locales' => $managedLocales,
                'permissions'     => $permission->getPermissions(),
                'languages'       => $languages,
            )
        );
        $this->printResult(array(
                'result' => true,
                'key'    => $translation->getKey(),
                'html'   => $html,
            )
        );
    }

    /**
     * @Route("/save-comment/{projectId}", name="save_comment")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function saveCommentAction(Request $request, Project $project)
    {
        $this->init();

        $bundle   = $request->get('bundle');
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$bundle || !$request->get('key') || !$request->get('comment')){
            die('validation exception, request content = ' . $request->getContent());
        }

        $key = $this->getKeyRepository()->findOneBy(array(
                'project'  => $project,
                'bundle'   => $bundle,
                'key'      => $request->get('key'),
            )
        );
        if(!$key instanceof Key){
            die('key invalid');
        }
        $comment = $request->get('comment');
        $key->setComment($comment);
        $key->setUpdatedAt();
        $this->em->persist($key);
        $this->em->flush();
        $this->restService->resultOk(
            array(
                'comment' => $comment,
                'id_html' => $this->translationsManager->keyToHtmlId($key->getKey()),
            )
        );
    }

    /**
     * @param Message $msg
     * @param         $action
     *
     * @throws \Exception
     */
    protected function genericActionOnMessage(Message $msg, $action)
    {
        switch($action){
            case self::APPROVE:
                $msg->setApproved(true);
                break;

            case self::DISAPPROVE:
                $msg->setApproved(false);
                break;

            default:
                throw new \Exception("genericActionOnMessage: unknown action " . $action);
        }

        $this->em->persist($msg);
        $this->em->flush($msg);
        $this->translationsManager->saveLog($msg, $action, $this->user);
    }

    /**
     * @param $locale
     * @param $perm
     *
     * @return bool
     */
    protected function checkPermission($locale, $perm)
    {
        $permissionArray = $this->user->getPermission();
        $permission      = null;
        $permissions     = isset($permissionArray[Permission::LOCALE_KEY]) ? $permissionArray[Permission::LOCALE_KEY] : array();
        if (isset($permissions[$locale])) {
            $permission = $permissions[$locale];
        } else {
            $permission = isset($permissions[Permission::WILD_KEY]) ? $permissions[Permission::WILD_KEY] : '';
        }

        return Permission::checkPermission($permission, $perm);
    }

    /**
     * @Route("/approve-translation/message/{messageId}", name="approve_translation")
     * @ Method("POST")
     * @ParamConverter("message", class="TranslationsBundle:Message", options={"id" = "messageId"})
     */
    public function approveMessageAction(Message $message)
    {
        $this->init();
        $lang = $message->getLanguage();
        if($this->checkPermission($lang, Permission::ADMIN_PERM)){
            $this->genericActionOnMessage($message, self::APPROVE);
            $this->restService->resultOk(
                array(
                    'message'  => $message->getId(),
                    'approved' => $message->getApproved(),
                    'id'       => $message->getId(),
                )
            );
        }else{
            $this->restService->exception(
                $this->translator->trans('message.without_permissions_to_approve')
            );
        }

    }

    /**
     * @Route("/disapprove-translation/message/{messageId}", name="disapprove_translation")
     * @Method("POST")
     * @ParamConverter("message", class="TranslationsBundle:Message", options={"id" = "messageId"})
     */
    public function disapproveMessageAction(Message $message)
    {
        $this->init();
        $lang = $message->getLanguage();
        if($this->checkPermission($lang, Permission::ADMIN_PERM)){
            $this->genericActionOnMessage($message, self::DISAPPROVE);
            $this->restService->resultOk(
                array(
                    'message'  => $message->getId(),
                    'approved' => $message->getApproved(),
                    'id'       => $message->getId(),
                )
            );
        }else{
            $this->restService->exception(
                $this->translator->trans('message.without_permissions_to_disapprove')
            );
        }

    }



    /**
     * @Route("/save-message/{projectId}", name="save_message")
     * @Method("POST")
     * @ParamConverter("project", class="TranslationsBundle:Project", options={"id" = "projectId"})
     */
    public function saveMessageAction(Request $request, Project $project)
    {
        $this->init();

        //$params   = json_decode($request->getContent(), true);
        $catalog  = $request->get('catalog');
        $locale   = $request->get('locale');
        $key      = $request->get('key');
        $message  = str_replace("\'","'",$request->get('message'));
        //@TODO: comprobar que el usuario que esta logado tiene permiso para hacer esto
        if(!$catalog || !$locale || !$key || !$message){
            die('validation exception, request content = ' . $request->getContent());
        }
        /** @var Translation $translation */
        $translation = $this->getTranslationRepository()->findOneBy(array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'key'       => $key,
            )
        );
        if(!$translation){
            $this->printResult(array(
                    'result' => false,
                    'reason' => 'translation not found',
                )
            );
        }
        $translations = $translation->getTranslations();
        if(!isset($translations[$locale])){
            $translations[$locale] = array(
                'message' => '',
                'approved' => false,
                'updatedAt' => null,
            );
        }
        $translations[$locale]['message'] = $message;
        $translations[$locale]['updatedAt'] = new \DateTime();
        $translation->setTranslations($translations);

        $this->dm->persist($translation);
        $this->dm->flush();

        $this->translationsManager->saveLog($translation->getId(), $locale, $message, TranslationLog::TRANSLATE, $this->user);

        $this->printResult(array(
                'result'  => true,
                'message' => $message,
            )
        );
    }

    /**
     * @return ProjectRepository
     */
    protected function getProjectRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Project');
    }

    /**
     * @return MessageRepository
     */
    protected function getMessageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Message');
    }

    /**
     * @return LanguageRepository
     */
    protected function getLanguageRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Language');
    }

    /**
     * @return KeyRepository
     */
    protected function getKeyRepository()
    {
        return $this->em->getRepository('TranslationsBundle:Key');
    }

    /**
     * @return TranslationRepository
     */
    protected function getTranslationRepository()
    {
        return $this->dm->getRepository('TranslationsBundle:Translation');
    }


}
