<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    public function test_ecommerce_relationships_are_defined(): void
    {
        $this->assertInstanceOf(
            HasMany::class,
            (new User)->orders()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            (new Order)->user()
        );

        $this->assertInstanceOf(
            HasMany::class,
            (new Order)->items()
        );

        $this->assertInstanceOf(
            HasOne::class,
            (new Order)->payment()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            (new OrderItem)->order()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            (new OrderItem)->product()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            (new Payment)->order()
        );

        $this->assertInstanceOf(
            HasMany::class,
            (new Product)->orderItems()
        );
    }

    public function test_user_supports_jwt_authentication(): void
    {
        $this->assertInstanceOf(
            JWTSubject::class,
            new User()
        );
    }

    public function test_admin_role_cannot_be_mass_assigned(): void
    {
        $this->assertNotContains(
            'is_admin',
            (new User)->getFillable()
        );
    }
}
