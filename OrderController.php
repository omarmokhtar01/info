<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Models\Cart;
use App\Models\Size;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\OrderItem;
use App\Models\Shipping;
use Illuminate\Support\Facades\DB as FacadesDB;
class OrderController extends Controller
{
    public function addToCart(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'size_id' => 'required|exists:sizes,id',
        'color_id' => 'required|exists:colors,id',
        'quantity' => 'required|integer|min:1'
    ]);

    $variant = ProductVariant::where('product_id', $request->product_id)
        ->where('size_id', $request->size_id)
        ->where('color_id', $request->color_id)
        ->first();

    if (!$variant) {
        return response()->json(['message' => 'Variant not found'], 404);
    }

    $sessionId = $request->session_id ?? session()->getId();
    $userId = auth()->check() ? auth()->id() : null;

    // البحث عن المنتج في السلة
    $cartItem = Cart::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })
        ->where('product_variant_id', $variant->id)
        ->first();

    if ($cartItem) {
        // تحديث الكمية فقط
        $cartItem->update([
            'quantity' => $cartItem->quantity + $request->quantity
        ]);
    } else {
        // إنشاء عنصر جديد في السلة
        $cartItem = Cart::create([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'product_variant_id' => $variant->id,
            'quantity' => $request->quantity
        ]);
    }

    return response()->json([
        'message' => 'Added to cart',
        'cart' => $cartItem,
        'session_id' => $sessionId
    ]);
}

public function transferCartToUser(Request $request)
{
    if (!auth()->check()) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $userId = auth()->id();
    $sessionId = $request->session_id;

    $sessionCartItems = Cart::where('session_id', $sessionId)->get();

    foreach ($sessionCartItems as $sessionItem) {
        $existingCartItem = Cart::where([
            'user_id' => $userId,
            'product_variant_id' => $sessionItem->product_variant_id
        ])->first();

        if ($existingCartItem) {
            // إذا كان المنتج موجودًا بالفعل، قم بتحديث الكمية فقط
            $existingCartItem->update([
                'quantity' => $existingCartItem->quantity + $sessionItem->quantity
            ]);

            // حذف العنصر المنسوخ من session
            $sessionItem->delete();
        } else {
            // نقل العنصر إلى المستخدم
            $sessionItem->update([
                'user_id' => $userId,
                'session_id' => null
            ]);
        }
    }

    return response()->json(['message' => 'Cart transferred to user']);
}

public function updateCart(Request $request)
{
    $request->validate([
        'product_variant_id' => 'required|exists:product_variants,id',
        'quantity' => 'required|integer|min:1'
    ]);

    $sessionId = $request->session_id ?? session()->getId();
    $userId = auth()->check() ? auth()->id() : null;

    $cartItem = Cart::where(function ($query) use ($userId, $sessionId) {
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }
    })->where('product_variant_id', $request->product_variant_id)->first();

    if (!$cartItem) {
        return response()->json(['message' => 'Cart item not found'], 404);
    }

    // تحديث الكمية الجديدة
    $cartItem->update(['quantity' => $request->quantity]);

    return response()->json([
        'message' => 'Cart updated successfully',
        'cart' => $cartItem->fresh()
    ]);
}

public function getCart(Request $request)
{
    $sessionId = $request->session_id ?? session()->getId();
    $userId = auth()->check() ? auth()->id() : null;

    $cartItems = Cart::where(function ($query) use ($userId, $sessionId) {
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }
    })->with(['productVariant.product', 'productVariant.size', 'productVariant.color'])->get();

    return response()->json([
        'message' => 'Cart retrieved successfully',
        'cart' => $cartItems
    ]);
}

    // 🛒 إنشاء طلب جديد
    public function createOrder(Request $request)
    {
        $request->validate([
            'session_id' => 'nullable|string',
            'shipping_id' => 'required|exists:shippings,id',
            'first_name' => 'nullable|string',
        'last_name' => 'nullable|string',
        'email' => 'nullable|email',
        'address' => 'nullable|string',
        'city' => 'nullable|string',
        'phone' => 'nullable|string',
        ]);
    
        $userId = auth()->check() ? auth()->id() : null;
        $sessionId = $userId ? null : $request->session_id;
    
        // جلب سلة المشتريات بناءً على `session_id` أو `user_id`
        $cartItems = Cart::with(['productVariant.product'])->where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->get();
    
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }
    
        // حساب مجموع أسعار المنتجات فقط
        $subtotal = $cartItems->sum(fn($item) => $item->quantity * $item->productVariant->product->price_after_discount);
    
        // جلب سعر الشحن من جدول `shippings`
        $shipping = Shipping::find($request->shipping_id);
        $shippingPrice = $shipping ? $shipping->price : 0; // إذا لم يكن هناك سعر، الافتراضي 0
    
        // حساب المبلغ الكلي (Subtotal + Shipping Price)
        $totalPrice = $subtotal + $shippingPrice;
    
        FacadesDB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $userId,
                'session_id' => $sessionId,

                'cart_id' => null,
                'subtotal' => $subtotal, // ✅ حفظ `subtotal`
                'shipping_price' => $shippingPrice, // ✅ حفظ `shipping_price`
                'total_price' => $totalPrice, // ✅ `subtotal + shipping_price`
                'status' => 'pending',
                'is_paid' => false,
                'shipping_id' => $request->shipping_id,
                'email' => $request->email,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'address' => $request->address,
        'city' => $request->city,
        'phone' => $request->phone,
            ]);
    
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->productVariant->product->price_after_discount,
                ]);
            }
    
            // حذف السلة بعد إنشاء الطلب
            Cart::where(function ($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })->delete();
    
            FacadesDB::commit();
    
            return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);
        } catch (\Exception $e) {
            FacadesDB::rollBack();
            return response()->json(['message' => 'Failed to create order', 'error' => $e->getMessage()], 500);
        }
    }
    
    // 📦 عرض جميع الطلبات الخاصة بالمستخدم أو `session_id`
    public function getOrders(Request $request)
    {
        $userId = auth()->check() ? auth()->id() : null;
        $sessionId = $userId ? null : $request->session_id;
    
        if (!$userId && !$sessionId) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        $orders = Order::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId); // ✅ استخدم session_id مباشرة
            }
        })->with('orderItems.productVariant.product')->get();
    
        return response()->json(['message' => 'Orders retrieved successfully', 'orders' => $orders]);
    }
    

    // 📄 عرض تفاصيل طلب معين
    public function getOrder($id, Request $request)
    {
        $sessionId = $request->session_id ?? session()->getId();
        $userId = auth()->check() ? auth()->id() : null;
    
        $order = Order::where('id', $id)
            ->where(function ($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId); // ✅ استخدم session_id مباشرة
                }
            })
            ->with('orderItems.productVariant.product')
            ->first();
    
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
    
        return response()->json(['message' => 'Order retrieved successfully', 'order' => $order]);
    }
    

    // 🗑️ حذف طلب معين
    public function deleteOrder($id, Request $request)
    {
        $sessionId = $request->session_id ?? session()->getId();
        $userId = auth()->check() ? auth()->id() : null;

        $order = Order::where('id', $id)->where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->whereHas('orderItems.productVariant.carts', function ($cartQuery) use ($sessionId) {
                    $cartQuery->where('session_id', $sessionId);
                });
            }
        })->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }

    // 🔄 تحديث حالة الطلب (Admin فقط)
    public function updateOrderStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled'
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->update(['status' => $request->status]);
        return response()->json(['message' => 'Order status updated', 'order' => $order]);
    }
    public function removeFromCart(Request $request)
{
    $request->validate([
        'product_variant_id' => 'required|exists:carts,product_variant_id',
    ]);

    $userId = auth()->id(); // الحصول على ID المستخدم إذا كان مسجلاً الدخول

    $query = Cart::where('product_variant_id', $request->product_variant_id);

    if ($userId) {
        // حذف العنصر بناءً على user_id إذا كان المستخدم مسجل دخول
        $query->where('user_id', $userId);
    } elseif ($request->has('session_id')) {
        // حذف العنصر بناءً على session_id إذا كان زائراً
        $query->where('session_id', $request->session_id);
    } else {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $cartItem = $query->first();

    if (!$cartItem) {
        return response()->json(['message' => 'Item not found in cart'], 404);
    }

    $cartItem->delete();

    return response()->json(['message' => 'Item removed from cart successfully']);
}

    
}