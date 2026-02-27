package com.example.pidev.controller.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.EmailService;
import com.example.pidev.service.user.PasswordResetService;
import com.example.pidev.service.user.UserService;
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

    // 👇 NOUVEAU : Bouton pour tester le lien (optionnel)
    @FXML private Button testLinkButton;

    private PasswordResetService resetService;
    private UserService userService;

    // 👇 Stocker le dernier token pour le test
    private String lastToken;

    @FXML
    public void initialize() {
        resetService = new PasswordResetService();
        userService = new UserService();

        if (loadingIndicator != null) {
            loadingIndicator.setVisible(false);
        }

        // 👇 Cacher le bouton de test par défaut
        if (testLinkButton != null) {
            testLinkButton.setVisible(false);
        }

        testEmailConfiguration();
    }

    private void testEmailConfiguration() {
        System.out.println("=== TEST CONFIGURATION EMAIL AU DÉMARRAGE ===");
        try {
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
                UserModel user = userService.getUserByEmail(email);

                javafx.application.Platform.runLater(() -> {
                    if (user == null) {
                        showStatus("❌ Aucun compte trouvé avec cet email", "red");
                        sendResetLinkBtn.setDisable(false);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                        return;
                    }

                    try {
                        // Créer un token
                        PasswordResetToken token = new PasswordResetToken(user.getId_User());
                        resetService.createToken(token);

                        if (token != null) {
                            // Stocker le token pour le test
                            lastToken = token.getToken();

                            // Afficher le token dans la console
                            System.out.println("🔑 Token généré: " + token.getToken());
                            System.out.println("🔗 Lien de réinitialisation: http://localhost:8080/reset-password?token=" + token.getToken());

                            // Envoyer l'email avec le token
                            EmailService.sendResetPasswordEmail(email, user.getFirst_Name(), token.getToken());

                            showStatus("✅ Email envoyé! Vérifiez votre boîte de réception", "green");

                            // 👇 Afficher le bouton de test
                            if (testLinkButton != null) {
                                testLinkButton.setVisible(true);
                                testLinkButton.setText("Tester le lien (token: " + token.getToken().substring(0, 8) + "...)");
                            }

                            // ✅ SUPPRIMÉ : openResetPasswordWindow(token.getToken());
                            // La fenêtre ne s'ouvre plus automatiquement !

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

    // 👇 NOUVELLE MÉTHODE : Pour tester le lien sans navigateur
    @FXML
    private void handleTestLink() {
        if (lastToken != null && !lastToken.isEmpty()) {
            openResetPasswordWindow(lastToken);
        } else {
            showStatus("❌ Aucun lien récent à tester", "red");
        }
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

            // ✅ NE PAS fermer la fenêtre actuelle
            // L'utilisateur peut vouloir revenir en arrière

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