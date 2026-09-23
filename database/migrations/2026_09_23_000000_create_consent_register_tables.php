<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The register, in the same shape as the Craft CMS plugin's: decisions, and
 * the screens they were made on, deduplicated by fingerprint.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('consent_presentations')) {
            Schema::create('consent_presentations', function (Blueprint $table): void {
                $table->id();
                $table->char('hash', 64)->unique();
                $table->string('language', 16);
                $table->text('payload');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('consent_decisions')) {
            return;
        }

        Schema::create('consent_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presentationId');

            $table->string('siteHandle');
            $table->string('userId')->nullable();
            $table->string('language', 16);
            $table->dateTime('decidedAt');
            $table->string('action', 16);
            $table->string('origin', 16);
            $table->text('categories');
            $table->string('outcome', 8);
            $table->integer('consentVersion');
            $table->text('policyUrl')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('userAgent', 512)->nullable();
            $table->timestamps();

            $table->index('decidedAt');
            $table->index('outcome');
            $table->index(['siteHandle', 'decidedAt']);
            $table->index('presentationId');

            $table->foreign('presentationId')->references('id')->on('consent_presentations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_decisions');
        Schema::dropIfExists('consent_presentations');
    }
};
