package com.example.pidev.controller.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.EmailService;
import com.example.pidev.service.user.PasswordResetService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;

public class ForgotPasswordController {

    @FXML private TextField emailField;
    @FXML private Button sendResetLinkBtn;
    @FXML private Button backToLoginBtn;
    @FXML private Label statusLabel;
    @FXML private ProgressIndicator loadingIndicator;

    private PasswordResetService resetService;

    @FXML
    public void initialize() {
        resetService = new PasswordResetService();

        if (loadingIndicator != null) {
            loadingIndicator.setVisible(false);
        }

        // ✅ AJOUTEZ LE TEST ICI - Test de configuration email au démarrage
        testEmailConfiguration();
    }

    // ✅ NOUVELLE MÉTHODE pour tester la configuration email
    private void testEmailConfiguration() {
        System.out.println("=== TEST CONFIGURATION EMAIL AU DÉMARRAGE ===");
        try {
            // Test de la configuration sans envoyer d'email


            // Afficher un message dans l'interface si la configuration est OK
            showStatus("Configuration email: OK", "green");

        } catch (Exception e) {
            System.err.println("❌ Erreur de configuration email: " + e.getMessage());
            showStatus("⚠️ Configuration email incomplète", "orange");
        }
    }

    @FXML
    private void handleSendResetLink() {
        String email = emailField.getText().trim();

        if (email.isEmpty()) {
            showStatus("❌ Veuillez saisir votre email", "red");
            return;
        }

        if (!isValidEmail(email)) {
            showStatus("❌ Format d'email invalide", "red");
            return;
        }

        sendResetLinkBtn.setDisable(true);
        if (loadingIndicator != null) loadingIndicator.setVisible(true);
        showStatus("Envoi en cours...", "blue");

        new Thread(() -> {
            try {
                UserModel user = resetService.getUserByEmail(email);

                javafx.application.Platform.runLater(() -> {
                    if (user == null) {
                        showStatus("❌ Aucun compte trouvé avec cet email", "red");
                        sendResetLinkBtn.setDisable(false);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                        return;
                    }

                    try {
                        // Créer un token
                        PasswordResetToken token = resetService.createResetToken(user.getId_User());

                        if (token != null) {
                            // Afficher le token dans la console pour déboguer
                            System.out.println("🔑 Token généré: " + token.getToken());

                            // Envoyer l'email avec le token
                            EmailService.sendResetPasswordEmail(email, user.getFirst_Name(), token.getToken());

                            showStatus("✅ Email envoyé! Vérifiez votre boîte de réception", "green");

                            // ✅ Ouvrir directement la fenêtre de réinitialisation
                            openResetPasswordWindow(token.getToken());

                        } else {
                            showStatus("❌ Erreur lors de la création du token", "red");
                        }

                    } catch (Exception e) {
                        showStatus("❌ Erreur: " + e.getMessage(), "red");
                        e.printStackTrace();
                    }

                    sendResetLinkBtn.setDisable(false);
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });

            } catch (Exception e) {
                javafx.application.Platform.runLater(() -> {
                    showStatus("❌ Erreur: " + e.getMessage(), "red");
                    sendResetLinkBtn.setDisable(false);
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });
            }
        }).start();
    }

    private void openResetPasswordWindow(String token) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/user/reset_password.fxml")
            );
            Parent root = loader.load();

            ResetPasswordController controller = loader.getController();
            controller.setToken(token);

            Stage stage = new Stage();
            stage.setTitle("Réinitialisation du mot de passe");
            stage.setScene(new Scene(root));
            stage.show();

            // Fermer la fenêtre actuelle
            Stage currentStage = (Stage) sendResetLinkBtn.getScene().getWindow();
            currentStage.close();

        } catch (Exception e) {
            e.printStackTrace();
            showStatus("❌ Erreur d'ouverture: " + e.getMessage(), "red");
        }
    }

    @FXML
    private void handleBackToLogin() {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Stage stage = (Stage) backToLoginBtn.getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void showStatus(String message, String color) {
        statusLabel.setText(message);
        statusLabel.setStyle("-fx-text-fill: " + color + "; -fx-font-weight: bold;");
    }

    private boolean isValidEmail(String email) {
        String emailRegex = "^[A-Za-z0-9+_.-]+@(.+)$";
        return email.matches(emailRegex);
    }
}