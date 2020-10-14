<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use EWZ\SymfonyAdminBundle\Event\UserEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Emails events.
 */
class EmailSubscriber implements EventSubscriberInterface
{
    /** @var \Twig_Environment */
    protected $twig;

    /** @var \Swift_Mailer */
    protected $mailer;

    /** @var UrlGeneratorInterface */
    protected $urlGenerator;

    /** @var string */
    protected $sender;

    /**
     * @param \Twig_Environment     $twig
     * @param \Swift_Mailer         $mailer
     * @param UrlGeneratorInterface $urlGenerator
     * @param string                $sender
     */
    public function __construct(
        \Twig_Environment $twig,
        \Swift_Mailer $mailer,
        UrlGeneratorInterface $urlGenerator,
        string $sender
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->sender = $sender;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::RESETTING_PASSWORD_SENT => 'onResettingPasswordSent',
        ];
    }

    /**
     * @param UserEvent $event
     */
    public function onResettingPasswordSent(UserEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $url = $this->urlGenerator->generate('resetting_reset', [
            'token' => $user->getConfirmationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $context = [
            'user' => $user,
            'confirmationUrl' => $url,
        ];

        $this->sendMessage('@SymfonyAdmin/resetting/email.txt.twig', $context, $user->getEmail());
    }

    /**
     * @param string       $templateName
     * @param array        $context
     * @param string|array $toEmail
     */
    protected function sendMessage(string $templateName, array $context, $toEmail): void
    {
        if (empty($toEmail)) {
            return;
        } elseif (!is_array($toEmail)) {
            $toEmail = [$toEmail];
        }

        $template = $this->twig->load($templateName);
        $subject = $template->renderBlock('subject', $context);
        $textBody = $template->renderBlock('body_text', $context);

        $htmlBody = null;

        if ($template->hasBlock('body_html', $context)) {
            $htmlBody = $template->renderBlock('body_html', $context);
        }

        $message = (new \Swift_Message())
            ->setSubject($subject)
            ->setFrom($this->sender)
            ->setTo(array_filter($toEmail));

        if (!empty($htmlBody)) {
            $message
                ->setBody($htmlBody, 'text/html')
                ->addPart($textBody, 'text/plain');
        } else {
            $message->setBody($textBody);
        }

        $this->mailer->send($message);
    }
}
