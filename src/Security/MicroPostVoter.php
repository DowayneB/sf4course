<?php
/**
 * Created by PhpStorm.
 * User: dowayneb
 * Date: 1/31/19
 * Time: 3:11 PM
 */

namespace App\Security;


use App\Entity\MicroPost;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MicroPostVoter extends Voter
{
    const EDIT = 'edit';
    const DELETE = 'delete';
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AccessDecisionManagerInterface
     */
    private $decisionManager;

    public function __construct(LoggerInterface $logger,AccessDecisionManagerInterface $decisionManager)
    {
        $this->logger = $logger;
        $this->decisionManager = $decisionManager;
    }


    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute,[self::EDIT,self::DELETE]))
        {
            return false;
        }
        if (!$subject instanceof MicroPost)
        {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->decisionManager->decide($token,[User::ROLE_ADMIN]))
        {
            return true;
        }
        $this->logger->info('voter is called');
        // TODO: Implement voteOnAttribute() method.
        $authenticatedUser = $token->getUser();

        if (!$authenticatedUser instanceof User)
        {
            return false;
        }

        /** @var MicroPost $microPost */
        $microPost = $subject;
        return $microPost->getUser()->getId() === $authenticatedUser->getId();
    }

}