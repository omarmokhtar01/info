<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable();

            $table->decimal('total_price', 10, 2);
            $table->string('status')->default('pending'); // [pending, processing, shipped, delivered, cancelled]
            $table->boolean('is_paid')->default(0);
            $table->foreignId('shipping_id')->nullable()->constrained()->onDelete('set null'); // إذا تم حذف طريقة الشحن لا نحذف الطلب
            $table->string('payment_method')->nullable(); // إضافة طريقة الدفع
            $table->text('shipping_address')->nullable(); // إضافة عنوان الشحن مباشرة


            $table->string('email')->nullable(); 
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->text('address')->nullable();
        $table->string('city')->nullable();
        $table->string('phone')->nullable();
            $table->timestamps();
        });
        
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
