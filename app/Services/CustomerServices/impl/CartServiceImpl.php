<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\Cart;
use App\Models\Customer\CartItems;
use App\Models\Owner\OwnerPackage;
use App\Models\Owner\OwnerProduct;
use App\Services\CustomerServices\CartItemsService;
use App\Services\CustomerServices\CartService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CartServiceImpl implements CartService
{
    public function __construct(private CartItemsService $cartItemsService) {}

    public function getOrCreateActiveCart(int $customerId): Cart
    {
        return DB::transaction(function () use ($customerId): Cart {

            DB::table('customer')
                ->where('id', $customerId)
                ->lockForUpdate()
                ->first();

            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($cart) {
                return $cart;
            }

            return Cart::create([
                'customer_id' => $customerId,
                'status' => 'active',
            ]);
        });
    }

    public function getCartItemCount(int $customerId): int
{
    $cart = Cart::query()
        ->where('customer_id', $customerId)
        ->where('status', 'active')
        ->first();

    if (!$cart) {
        return 0;
    }

    return CartItems::query()
        ->where('cart_id', $cart->id)
        ->count();
}

    public function addToCart(int $customerId, int $productId, int $quantity, ?int $packageId = null): array
    {
        return DB::transaction(function () use ($customerId, $productId, $quantity, $packageId): array {

            $cart = $this->getOrCreateActiveCart($customerId);

            /**
             * STEP 1: GET PRODUCT
             */
            $product = OwnerProduct::query()
                ->where('id', $productId)
                ->where('status', 1)
                ->first();

            if (!$product) {
                throw new RuntimeException('Product not found or unavailable.');
            }

            /**
             * STEP 2: SELECT PACKAGE
             */
            if ($packageId) {
                $package = OwnerPackage::query()
                    ->where('id', $packageId)
                    ->where('owner_product_id', $productId)
                    ->first();

                if (!$package) {
                    throw new RuntimeException('Invalid package selected.');
                }
            } else {
                $package = OwnerPackage::query()
                    ->where('owner_product_id', $productId)
                    ->where('is_default', 1)
                    ->first();

                if (!$package) {
                    throw new RuntimeException('No default package found.');
                }
            }

            /**
             * STEP 2.5: VALIDATE STOCK & LIMIT
             */
            if ($quantity < 1) {
                throw new RuntimeException('Quantity must be at least 1.');
            }

            if ($quantity > 10) {
                throw new RuntimeException('Maximum 10 items allowed per product.');
            }

            if ($package->stock_quantity <= 0) {
                throw new RuntimeException('This package is out of stock.');
            }

            // existing cart item
            $existingItem = CartItems::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->where('package_id', $package->id)
                ->first();

            $currentQty = $existingItem?->quantity ?? 0;

            $newQty = $currentQty + $quantity;

            // limit total cart qty
            if ($newQty > 10) {
                throw new RuntimeException('Maximum 10 items allowed per product.');
            }

            // stock validation
            if ($newQty > $package->stock_quantity) {
                throw new RuntimeException(
                    'Only ' . $package->stock_quantity . ' items available in stock.'
                );
            }

            /**
             * STEP 3: PRICE CALCULATION (SAFE FORMAT)
             */
            $unitPrice = number_format((float) $package->price, 2, '.', '');
            $lineTotal = number_format($unitPrice * $quantity, 2, '.', '');

            /**
             * STEP 4: SAVE CART ITEM
             */
            $item = $this->cartItemsService->addOrUpdateItem(
                $cart->id,
                $product->id,
                (int) $product->owner_id,
                $package->id,
                $quantity,
                $unitPrice,
                $lineTotal
            );

            /**
             * STEP 5: RETURN UPDATED CART
             */
            $cart->load([
                'items.product:id,product_name,main_image',
                'items.package:id,package_name,price'
            ]);

            return [
                'cart' => $cart,
                'item' => $item
            ];
        });
    }

    public function getCart(int $customerId, int $perPage): array
    {
        $cart = Cart::query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return [
                'cart' => null,
                'items' => collect([]),
            ];
        }

        $items = $cart->items()
            ->with([
                // ✅ FIX: load owner + setting (IMPORTANT)
                'product.owner.setting:id,owner_id,pharmacy_name',

                'package:id,package_name,price,stock_quantity',
            ])
            ->select([
                'id',
                'cart_id',
                'product_id',
                'package_id',
                'quantity',
                'unit_price',
                'line_total',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {

                return [
                    'id' => $item->id,
                    'cart_id' => $item->cart_id,
                    'product_id' => $item->product_id,
                    'package_id' => $item->package_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,

                    // ✅ PRODUCT
                    'product' => [
                        'id' => $item->product->id,
                        'product_name' => $item->product->product_name,
                        'main_image' => $item->product->main_image,

                        // ⭐ IMPORTANT FOR GROUPING
                        'owner_id' => $item->product->owner_id,

                        'pharmacy_name' =>
                        $item->product->owner->setting->pharmacy_name ?? 'Unknown Pharmacy',
                    ],

                    // ✅ PACKAGE
                    'package' => [
                        'id' => $item->package->id,
                        'package_name' => $item->package->package_name,
                        'price' => $item->package->price,
                        'stock'=> $item->package->stock_quantity
                    ],
                ];
            });

        return [
            'cart' => $cart,
            'items' => $items,
        ];
    }


    public function updateItem(int $customerId, int $cartItemId, int $quantity): array
    {
        return DB::transaction(function () use ($customerId, $cartItemId, $quantity) {

            $item = CartItems::query()
                ->with('package')
                ->where('id', $cartItemId)
                ->first();

            if (!$item) {
                throw new RuntimeException('Cart item not found.');
            }

            // security
            if ($item->cart->customer_id !== $customerId) {
                throw new RuntimeException('Unauthorized.');
            }

            /**
             * VALIDATE QUANTITY
             */
            if ($quantity < 1) {
                throw new RuntimeException('Quantity must be at least 1.');
            }

            if ($quantity > 10) {
                throw new RuntimeException('Maximum 10 items allowed per product.');
            }

            /**
             * VALIDATE PACKAGE
             */
            if (!$item->package) {
                throw new RuntimeException('Package not found.');
            }

            if ($item->package->stock_quantity <= 0) {
                throw new RuntimeException('This package is out of stock.');
            }

            /**
             * VALIDATE STOCK
             */
            if ($quantity > $item->package->stock_quantity) {
                throw new RuntimeException(
                    'Only ' . $item->package->stock_quantity . ' items available in stock.'
                );
            }

            /**
             * UPDATE ITEM
             */
            $item->quantity = $quantity;

            $item->line_total = number_format(
                $quantity * (float) $item->unit_price,
                2,
                '.',
                ''
            );

            $item->save();

            $item->load([
                'product:id,product_name,main_image',
                'package:id,package_name,price,stock_quantity'
            ]);

            return [
                'item' => $item
            ];
        });
    }

    public function removeItem(int $customerId, int $cartItemId): void
    {
        DB::transaction(function () use ($customerId, $cartItemId) {

            $item = CartItems::query()
                ->where('id', $cartItemId)
                ->first();

            if (!$item) {
                throw new RuntimeException('Cart item not found.');
            }

            if ($item->cart->customer_id !== $customerId) {
                throw new RuntimeException('Unauthorized.');
            }

            $item->delete();
        });
    }

    public function removeProduct(int $customerId, int $productId): void
    {
        DB::transaction(function () use ($customerId, $productId) {

            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                throw new RuntimeException('Cart not found.');
            }

            $deleted = CartItems::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->delete();

            if ($deleted === 0) {
                throw new RuntimeException('No items found for this product.');
            }
        });
    }
}
