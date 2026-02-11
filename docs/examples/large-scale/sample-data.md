# Sample Data Setup

Database seeders for testing large-scale exports.

## Overview

These seeders create realistic test data for performance testing:
- 10,000 customers
- 100,000+ orders with order items
- 500,000+ order items

## Customer Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding 10,000 customers...');

        $tiers = ['bronze', 'silver', 'gold', 'platinum'];
        $batchSize = 500;
        $total = 10000;

        for ($i = 0; $i < $total; $i += $batchSize) {
            $customers = [];

            for ($j = 0; $j < $batchSize; $j++) {
                $customers[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => fake()->company(),
                    'email' => fake()->unique()->companyEmail(),
                    'company' => fake()->company(),
                    'tier' => $tiers[array_rand($tiers)],
                    'created_at' => fake()->dateTimeBetween('-2 years', 'now'),
                    'updated_at' => now(),
                ];
            }

            Customer::insert($customers);
            $this->command->info("  Created " . min($i + $batchSize, $total) . " / {$total}");
        }
    }
}
```

## Order Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding 100,000 orders...');

        $customerIds = Customer::pluck('id')->toArray();
        $statuses = ['pending', 'processing', 'completed', 'cancelled'];
        $batchSize = 1000;
        $total = 100000;

        for ($i = 0; $i < $total; $i += $batchSize) {
            $orders = [];

            for ($j = 0; $j < $batchSize; $j++) {
                $orderNumber = $i + $j + 1;
                $status = $statuses[array_rand($statuses)];
                $createdAt = fake()->dateTimeBetween('-1 year', 'now');

                $orders[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'order_number' => 'ORD-' . str_pad($orderNumber, 8, '0', STR_PAD_LEFT),
                    'customer_id' => $customerIds[array_rand($customerIds)],
                    'status' => $status,
                    'total' => 0,  // Will be updated after items are added
                    'created_at' => $createdAt,
                    'updated_at' => $status === 'completed'
                        ? fake()->dateTimeBetween($createdAt, 'now')
                        : $createdAt,
                ];
            }

            Order::insert($orders);
            $this->command->info("  Created " . min($i + $batchSize, $total) . " / {$total}");
        }
    }
}
```

## Order Item Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding order items (1-10 per order)...');

        $productIds = Product::pluck('id')->toArray();
        $batchSize = 5000;

        Order::chunk(1000, function ($orders) use ($productIds, $batchSize) {
            $items = [];
            $orderTotals = [];

            foreach ($orders as $order) {
                $itemCount = rand(1, 10);
                $orderTotal = 0;

                for ($i = 0; $i < $itemCount; $i++) {
                    $quantity = rand(1, 5);
                    $price = rand(1000, 50000) / 100;  // $10.00 - $500.00
                    $subtotal = $quantity * $price;
                    $orderTotal += $subtotal;

                    $items[] = [
                        'id' => \Illuminate\Support\Str::uuid(),
                        'order_id' => $order->id,
                        'product_id' => $productIds[array_rand($productIds)],
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $subtotal,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];

                    if (count($items) >= $batchSize) {
                        OrderItem::insert($items);
                        $items = [];
                    }
                }

                $orderTotals[$order->id] = $orderTotal;
            }

            // Insert remaining items
            if (!empty($items)) {
                OrderItem::insert($items);
            }

            // Update order totals
            foreach ($orderTotals as $orderId => $total) {
                Order::where('id', $orderId)->update(['total' => $total]);
            }

            $this->command->info("  Processed " . $orders->count() . " orders");
        });
    }
}
```

## Product Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding 1,000 products...');

        $categories = ['Electronics', 'Clothing', 'Home', 'Sports', 'Books'];
        $products = [];

        for ($i = 0; $i < 1000; $i++) {
            $products[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => fake()->words(3, true),
                'sku' => 'SKU-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'category' => $categories[array_rand($categories)],
                'price' => rand(500, 100000) / 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Product::insert($products);
    }
}
```

## Database Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
            OrderItemSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('Summary:');
        $this->command->info('  Products: ' . \App\Models\Product::count());
        $this->command->info('  Customers: ' . \App\Models\Customer::count());
        $this->command->info('  Orders: ' . \App\Models\Order::count());
        $this->command->info('  Order Items: ' . \App\Models\OrderItem::count());
    }
}
```

## Running the Seeders

```bash
# Fresh database with seeders
php artisan migrate:fresh --seed

# Just run seeders (on existing database)
php artisan db:seed
```

## Expected Results

After seeding:
- 1,000 products
- 10,000 customers
- 100,000 orders
- ~500,000 order items (average 5 per order)

## Memory Optimization

For very large datasets, consider:

```php
// Disable model events during seeding
Order::unguard();
Order::withoutEvents(function () {
    // Seeding logic
});
Order::reguard();
```

## Verification

```php
// Check counts
echo "Products: " . Product::count();     // 1,000
echo "Customers: " . Customer::count();   // 10,000
echo "Orders: " . Order::count();         // 100,000
echo "Items: " . OrderItem::count();      // ~500,000

// Check relationships
$order = Order::with(['customer', 'items'])->first();
echo "Order has " . $order->items->count() . " items";
echo "Customer: " . $order->customer->name;
```

## Models

Ensure your models are set up:

```php
// Customer.php
class Customer extends Model
{
    use HasUuids;

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

// Order.php
class Order extends Model
{
    use HasUuids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

// OrderItem.php
class OrderItem extends Model
{
    use HasUuids;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

## Notes

- Use batch inserts for performance
- Chunk when processing existing data
- UUIDs are used for all primary keys
- Faker generates realistic data
