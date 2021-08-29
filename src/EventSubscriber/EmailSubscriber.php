<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use EWZ\SymfonyAdminBundle\Event\UserEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Emails events.
 */
class EmailSubscriber implements EventSubscriberInterface
{
    /** @var TwigEnvironment */
    protected $twig;

    /** @var MailerInterface */
    protected $mailer;

    /** @var UrlGeneratorInterface */
    protected $urlGenerator;

    /** @var string */
    protected $sender;

    /**
     * @param TwigEnvironment       $twig
     * @param MailerInterface       $mailer
     * @param UrlGeneratorInterface $urlGenerator
     * @param string                $sender
     */
    public function __construct(
        TwigEnvironment $twig,
        MailerInterface $mailer,
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
     * @param string              $templateName
     * @param array               $context
     * @param string|array        $toEmail
     * @param string|Address|null $sender
     */
    protected function sendMessage(string $templateName, array $context, $toEmail, $sender = null): void
    {
        if (empty($toEmail)) {
            return;
        } elseif (!\is_array($toEmail)) {
            $toEmail = [$toEmail];
        }

        $template = $this->twig->load($templateName);
        $subject = $template->renderBlock('subject', $context);
        $textBody = $template->renderBlock('body_text', $context);

        $htmlBody = null;

        if ($template->hasBlock('body_html', $context)) {
            $htmlBody = $template->renderBlock('body_html', $context);
        }

        $email = (new Email())
            ->from($sender ?: $this->sender)
            ->to(...array_filter($toEmail))
            ->subject($subject)
            ->text($textBody);

        if (!empty($htmlBody)) {
            $email->html($htmlBody);
        }

        $this->mailer->send($email);
    }
}
