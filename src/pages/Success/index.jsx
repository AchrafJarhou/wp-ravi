import { useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useDispatch } from "react-redux";
import { showToast } from "../../slices/toastSlice";
import OrderDetails from "../../components/OrderDetails";
import "./Success.scss";

export default function Success() {
  const { orderId } = useParams();
  const dispatch = useDispatch();
  const navigate = useNavigate();

  useEffect(() => {
    dispatch(showToast(`Commande n°${orderId} confirmée`));
  }, [orderId, dispatch]);

  return (
    <div className="success-page">
      <div className="success-container">
        <div className="success-header">
          <div className="success-icon">✓</div>
          <h1>Commande confirmée !</h1>
          <p className="order-number">Numéro de commande: <strong>#{orderId}</strong></p>
        </div>

        <div className="success-message">
          <p>Merci beaucoup pour votre achat ! 🎉</p>
          <p>Un email de confirmation a été envoyé avec les détails de votre commande.</p>
        </div>

        <div className="order-info">
          <div className="info-card">
            <h3>📧 Email de confirmation</h3>
            <p>Vérifiez votre boîte de réception pour le reçu et les informations de suivi.</p>
          </div>

          <div className="info-card">
            <h3>📦 Suivi de commande</h3>
            <p>Vous recevrez des mises à jour sur l'état de votre livraison par email.</p>
          </div>

          <div className="info-card">
            <h3>❓ Besoin d'aide ?</h3>
            <p>Contactez-nous à support@example.com pour toute question.</p>
          </div>
        </div>

        <OrderDetails orderId={orderId} />

        <div className="success-actions">
          <button
            className="btn btn-primary"
            onClick={() => navigate("/")}
          >
            Retour à la boutique
          </button>
          <button
            className="btn btn-secondary"
            onClick={() => navigate("/profile")}
          >
            Mes commandes
          </button>
        </div>
      </div>
    </div>
  );
}
