<?php

namespace App\Service;

use App\Entity\ModerationCase;
use App\Localization\SupportedLocale;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Crée les notifications émises lors des décisions de modération.
 */
final class ModerationNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendHiddenContentWarning(ModerationCase $case): void
    {
        $author = $case->getAuthor();
        if ($author === null || $author->getEmail() === null) {
            return;
        }

        try {
            $locale = SupportedLocale::normalize($author->getProfile()?->getLanguage());
            $reason = $this->translatedReason($case->getReason(), $locale);
            $this->mailer->send(
                (new Email())
                    ->from(new Address('admin@safecity.fr', 'SafeCity'))
                    ->to($author->getEmail())
                    ->subject($this->translator->trans('email.moderation.subject', locale: $locale))
                    ->text($this->translator->trans(
                        'email.moderation.body',
                        ['%reason%' => $reason],
                        locale: $locale,
                    ))
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('L’avertissement de modération par e-mail n’a pas pu être envoyé.', [
                'moderation_case_id' => $case->getId(),
                'exception' => $exception,
            ]);
        }
    }

    private function translatedReason(string $reason, string $locale): string
    {
        // Compatibilité avec les quelques dossiers créés avant les codes
        // de motif neutres par rapport à la langue.
        $reasonCode = match ($reason) {
            'Spam ou contenu répétitif' => 'spam',
            'Données personnelles exposées' => 'personal_data',
            'Contenu dangereux ou trompeur' => 'dangerous',
            'Contenu inapproprié' => 'inappropriate',
            'spam', 'personal_data', 'dangerous', 'inappropriate' => $reason,
            default => 'inappropriate',
        };

        return $this->translator->trans('moderation.reason.' . $reasonCode, locale: $locale);
    }
}