<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('code', 'features_code_index');
            $table->index('module', 'features_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
