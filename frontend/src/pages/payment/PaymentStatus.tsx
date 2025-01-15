import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '@/lib/axios';

export const PaymentStatus: React.FC = () => {
  const { orderId, status } = useParams();
  const navigate = useNavigate();
  const [order, setOrder] = useState<any>(null);

  useEffect(() => {
    const fetchOrder = async () => {
      try {
        const response = await api.get(`/orders/${orderId}`);
        setOrder(response.data);
      } catch (error) {
        console.error('Failed to fetch order:', error);
        navigate('/orders');
      }
    };

    if (orderId) {
      fetchOrder();
    }
  }, [orderId]);

  return (
    <div>
      <h1>{status === 'success' ? 'Payment Successful' : 'Payment Failed'}</h1>
      {order && (
        <div>
          <p>Order ID: {order.id}</p>
          <p>Amount: {order.total_amount} PKR</p>
          <p>Status: {order.status}</p>
        </div>
      )}
    </div>
  );
}; 