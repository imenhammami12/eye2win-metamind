<?php

namespace App\Entity;

enum NotificationType: string
{
    // Team notifications
    case TEAM_INVITATION = 'TEAM_INVITATION';
    case TEAM_REQUEST = 'TEAM_REQUEST';
    case TEAM_ACCEPTED = 'TEAM_ACCEPTED';
    case TEAM_REJECTED = 'TEAM_REJECTED';

    // Training
    case TRAINING_REMINDER = 'TRAINING_REMINDER';

    // Coach
    case COACH_APPLICATION = 'COACH_APPLICATION';
    case COACH_APPROVED = 'COACH_APPROVED';
    case COACH_REJECTED = 'COACH_REJECTED';
    case COACH_APPLICATION_STATUS = 'COACH_APPLICATION_STATUS';

    // Channel
    case CHANNEL_APPROVED = 'CHANNEL_APPROVED';
    case CHANNEL_REJECTED = 'CHANNEL_REJECTED';

    // Messages
    case MESSAGE_RECEIVED = 'MESSAGE_RECEIVED';

    // Complaints
    case COMPLAINT_SUBMITTED = 'COMPLAINT_SUBMITTED';
    case COMPLAINT_NEW = 'COMPLAINT_NEW';
    case COMPLAINT_ASSIGNED = 'COMPLAINT_ASSIGNED';
    case COMPLAINT_UPDATED = 'COMPLAINT_UPDATED';
    case COMPLAINT_RESPONDED = 'COMPLAINT_RESPONDED';
    case COMPLAINT_RESOLVED = 'COMPLAINT_RESOLVED';

    // Account
    case ACCOUNT_WARNING = 'ACCOUNT_WARNING';

    // System
    case SYSTEM = 'SYSTEM';

    public function getLabel(): string
    {
        return match($this) {
            self::TEAM_INVITATION => 'Invitation d\'équipe',
            self::TEAM_REQUEST => 'Demande de rejoindre une équipe',
            self::TEAM_ACCEPTED => 'Demande acceptée',
            self::TEAM_REJECTED => 'Demande refusée',

            self::TRAINING_REMINDER => 'Rappel d\'entraînement',

            self::COACH_APPLICATION => 'Demande de coach',
            self::COACH_APPROVED => 'Coach approuvé',
            self::COACH_REJECTED => 'Coach rejeté',
            self::COACH_APPLICATION_STATUS => 'Statut candidature coach',

            self::CHANNEL_APPROVED => 'Channel approuvé',
            self::CHANNEL_REJECTED => 'Channel rejeté',

            self::MESSAGE_RECEIVED => 'Nouveau message',

            self::COMPLAINT_SUBMITTED => 'Réclamation soumise',
            self::COMPLAINT_NEW => 'Nouvelle réclamation',
            self::COMPLAINT_ASSIGNED => 'Réclamation assignée',
            self::COMPLAINT_UPDATED => 'Réclamation mise à jour',
            self::COMPLAINT_RESPONDED => 'Réponse administrateur',
            self::COMPLAINT_RESOLVED => 'Réclamation résolue',

            self::ACCOUNT_WARNING => 'Avertissement',
            self::SYSTEM => 'Système',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::TEAM_INVITATION => '👥',
            self::TEAM_REQUEST => '📩',
            self::TEAM_ACCEPTED => '✅',
            self::TEAM_REJECTED => '❌',

            self::TRAINING_REMINDER => '⏰',

            self::COACH_APPLICATION => '📋',
            self::COACH_APPROVED => '🎓',
            self::COACH_REJECTED => '❌',
            self::COACH_APPLICATION_STATUS => '🎓',

            self::CHANNEL_APPROVED => '✅',
            self::CHANNEL_REJECTED => '❌',

            self::MESSAGE_RECEIVED => '💬',

            self::COMPLAINT_SUBMITTED => '📝',
            self::COMPLAINT_NEW => '🆕',
            self::COMPLAINT_ASSIGNED => '👤',
            self::COMPLAINT_UPDATED => '🔄',
            self::COMPLAINT_RESPONDED => '💬',
            self::COMPLAINT_RESOLVED => '✅',

            self::ACCOUNT_WARNING => '⚠️',
            self::SYSTEM => 'ℹ️',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::TEAM_INVITATION => 'primary',
            self::TEAM_REQUEST => 'info',
            self::TEAM_ACCEPTED => 'success',
            self::TEAM_REJECTED => 'danger',

            self::TRAINING_REMINDER => 'warning',

            self::COACH_APPLICATION,
            self::COACH_APPLICATION_STATUS => 'info',

            self::COACH_APPROVED => 'success',
            self::COACH_REJECTED => 'danger',

            self::CHANNEL_APPROVED => 'success',
            self::CHANNEL_REJECTED => 'danger',

            self::MESSAGE_RECEIVED => 'primary',

            self::COMPLAINT_SUBMITTED => 'info',
            self::COMPLAINT_NEW => 'warning',
            self::COMPLAINT_ASSIGNED => 'primary',
            self::COMPLAINT_UPDATED => 'info',
            self::COMPLAINT_RESPONDED => 'success',
            self::COMPLAINT_RESOLVED => 'success',

            self::ACCOUNT_WARNING => 'warning',

            self::SYSTEM => 'secondary',
        };
    }
}
