<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Squashed baseline for the current application schema.
 *
 * IF NOT EXISTS keeps this safe when upgrading an installation that already
 * has the schema. Existing installations should mark this baseline as applied
 * after replacing the previous migration history.
 */
final class Version20260727090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the squashed baseline schema for users, bookings, and manual PayPal payments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, lesson_credits INTEGER DEFAULT 0 NOT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TABLE IF NOT EXISTS lesson_booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, starts_at DATETIME NOT NULL, topic VARCHAR(80) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, student_id INTEGER NOT NULL, CONSTRAINT FK_1570BF79CB944F1A FOREIGN KEY (student_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_1570BF79CB944F1A ON lesson_booking (student_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_lesson_start ON lesson_booking (starts_at)');
        $this->addSql('CREATE TABLE IF NOT EXISTS lesson_payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, paypal_reference VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, student_id INTEGER NOT NULL, CONSTRAINT FK_9854D6AACB944F1A FOREIGN KEY (student_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_9854D6AADF59F226 ON lesson_payment (paypal_reference)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_lesson_payment_student ON lesson_payment (student_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS lesson_payment');
        $this->addSql('DROP TABLE IF EXISTS lesson_booking');
        $this->addSql('DROP TABLE IF EXISTS app_user');
    }
}
