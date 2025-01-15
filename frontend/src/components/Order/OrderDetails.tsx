import { PayFastCheckout } from '../Payment/PayFastCheckout';

export const OrderDetails: React.FC<{ order: Order }> = ({ order }) => {
    return (
        <div>
            <h2>Order #{order.id}</h2>
            <p>Total: {order.total_amount} PKR</p>
            
            {order.status === 'pending' && (
                <PayFastCheckout 
                    orderId={order.id} 
                    amount={order.total_amount} 
                />
            )}
        </div>
    );
}; 