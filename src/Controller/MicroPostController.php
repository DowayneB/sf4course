<?php
/**
 * Created by PhpStorm.
 * User: Emmanuel
 * Date: 08/05/2018
 * Time: 16:08
 */

namespace App\Controller;

use App\Entity\MicroPost;
use App\Entity\User;
use App\Form\MicroPostType;
use App\Repository\MicroPostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @Route("/micro-post")
 */
class MicroPostController
{

    /**
     * @var \Twig_Environment
     */
    private $twig;
    /**
     * @var MicroPostRepository
     */
    private $microPostRepository;
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var FlashBagInterface
     */
    private $flashBag;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    public function __construct(
        \Twig_Environment $twig,
        MicroPostRepository $microPostRepository,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        FlashBagInterface $flashBag,
        LoggerInterface $logger,
        AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->twig = $twig;
        $this->microPostRepository = $microPostRepository;
        $this->formFactory = $formFactory;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->flashBag = $flashBag;
        $this->logger = $logger;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @Route("/", name="micro_post_index")
     */
    public function index(TokenStorageInterface $tokenStorage, UserRepository $userRepository)
    {
        $currentUser = $tokenStorage->getToken()->getUser();

        $usersToFollow = [];


        if ($currentUser instanceof User)
        {
            $posts = $this->microPostRepository->findAllByUsers($currentUser->getFollowing());

            $usersToFollow = count($posts) === 0 ? $userRepository->findAllWithMoreThan5PostsExceptUser($currentUser) : [];
        } else {
            $posts = $this->microPostRepository->findBy([], ['time' => 'DESC']);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $html = $this->twig->render('micro-post/index.html.twig', [
            'posts' => $posts,
            'usersToFollow' => $usersToFollow
        ]);
        return new Response($html);
    }

    /**
     * @Route("/edit/{id}", name="micro_post_edit")
     * @Security("is_granted('edit',micropost)",message="access denied")
     */
    public function edit(MicroPost $micropost, Request $request)
    {
        //$this->denyUnlessGranted('edit', $micropost);
        if (!$this->authorizationChecker->isGranted('edit',$micropost)){
            throw new UnauthorizedHttpException();
        }
        $form = $this->formFactory->create(MicroPostType::class, $micropost);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
            $this->entityManager->flush();

            return new RedirectResponse($this->router->generate('micro_post_index'));
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return new Response(
            $this->twig->render('micro-post/add.html.twig', [
                'form' => $form->createView()
            ])
        );
    }


    /**
     * @Route("/delete/{id}", name="micro_post_delete")
     * @Security("is_granted('delete',micropost)",message="access denied")
     */
    public function delete(MicroPost $micropost)
    {
        if (!$this->authorizationChecker->isGranted('delete',$micropost)){
            throw new UnauthorizedHttpException();
        }
        $this->entityManager->remove($micropost);
        $this->entityManager->flush();

        $this->flashBag->add('notice', 'Micro post was deleted');

        return new RedirectResponse($this->router->generate('micro_post_index'));
    }

    /**
     * @Route("/add", name="micro_post_add")
     * @Security("is_granted('ROLE_USER')")
     */
    public function add(Request $request,TokenStorageInterface $tokenStorage)
    {
        $user = $tokenStorage->getToken()->getUser();

        $micropost = new MicroPost();
        $micropost->setUser($user);
        $form = $this->formFactory->create(MicroPostType::class, $micropost);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
            $this->entityManager->persist($micropost);
            $this->entityManager->flush();

            return new RedirectResponse($this->router->generate('micro_post_index'));
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        return new Response(
            $this->twig->render('micro-post/add.html.twig', [
                'form' => $form->createView()
            ])
        );
    }

    /**
     * @Route("/user/{username}",name="micro_post_user")
     */
    public function userPosts(User $userWithPosts)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $html = $this->twig->render('micro-post/user-post.html.twig',
            [
            'posts' => $this->microPostRepository->findBy(
                ['user' => $userWithPosts],
                ['time' => 'DESC']
            ),
                'user' => $userWithPosts,
/*              'posts' => $userWithPosts->getPosts(),*/
            ]
        );

        return new Response($html);
    }

    /**
     * @Route("/{id}", name="micro_post_post")
     */
    public function post(MicroPost $post)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new Response(
            $this->twig->render('micro-post/post.html.twig', [
                'post' => $post,
            ])
        );
    }
}