package com.example.pidev.controller.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.PasswordResetService;
import com.example.pidev.service.user.UserService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

public class ResetPasswordController {

    // IDs correspondant à TON FXML
    @FXML private Label tokenInfoLabel;
    @FXML private ProgressIndicator loadingIndicator;
    @FXML private VBox formBox;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;
    @FXML private Label passwordStrengthLabel;
    @FXML private Label statusLabel;
    @FXML private Button resetPasswordBtn;  // ← Changé de resetButton à resetPasswordBtn
    @FXML private Button backToLoginBtn;    // ← Changé de cancelButton à backToLoginBtn

    private String token;
    private PasswordResetService tokenService;
    private UserService userService;

    @FXML
    public void initialize() {
        tokenService = new PasswordResetService();
        userService = new UserService();

        if (loadingIndicator != null) {
            loadingIndicator.setVisible(false);
        }

        // Désactiver le bouton de réinitialisation par défaut
        if (resetPasswordBtn != null) {
            resetPasswordBtn.setDisable(true);
        }

        // Ajouter un listener pour vérifier la force du mot de passe
        if (newPasswordField != null) {
            newPasswordField.textProperty().addListener((obs, oldVal, newVal) -> {
                updatePasswordStrength(newVal);
            });
        }
    }
    public void setToken(String token) {
        this.token = token;
        System.out.println("🔑 Token reçu dans ResetPasswordController: " + token);

        if (tokenInfoLabel != null) {
            tokenInfoLabel.setText("⏳ Validation du lien en cours...");
        }

        // Valider le token
        if (tokenService == null) tokenService = new PasswordResetService();
        PasswordResetToken resetToken = tokenService.findByToken(token);

        if (resetToken == null || !resetToken.isValid()) {
            if (tokenInfoLabel != null) {
                tokenInfoLabel.setText("❌ Lien invalide ou expiré");
                tokenInfoLabel.setStyle("-fx-text-fill: red;");
            }
            if (resetPasswordBtn != null) {
                resetPasswordBtn.setDisable(true);
            }
            if (formBox != null) {
                formBox.setVisible(false);
                formBox.setManaged(false);
            }
        } else {
            if (tokenInfoLabel != null) {
                tokenInfoLabel.setText("✅ Lien valide - Veuillez entrer votre nouveau mot de passe");
                tokenInfoLabel.setStyle("-fx-text-fill: green;");
            }
            if (resetPasswordBtn != null) {
                resetPasswordBtn.setDisable(false);
            }
            if (formBox != null) {
                formBox.setVisible(true);
                formBox.setManaged(true);
            }
        }
    }

    private void updatePasswordStrength(String password) {
        if (password == null || password.isEmpty()) {
            passwordStrengthLabel.setText("");
            return;
        }

        int score = 0;
        if (password.length() >= 6) score++;
        if (password.matches(".*[A-Z].*")) score++;
        if (password.matches(".*[a-z].*")) score++;
        if (password.matches(".*\\d.*")) score++;

        switch (score) {
            case 0:
            case 1:
                passwordStrengthLabel.setText("🔴 Faible");
                passwordStrengthLabel.setStyle("-fx-text-fill: red;");
                break;
            case 2:
                passwordStrengthLabel.setText("🟡 Moyen");
                passwordStrengthLabel.setStyle("-fx-text-fill: orange;");
                break;
            case 3:
                passwordStrengthLabel.setText("🟢 Fort");
                passwordStrengthLabel.setStyle("-fx-text-fill: green;");
                break;
            case 4:
                passwordStrengthLabel.setText("🟢 Très fort");
                passwordStrengthLabel.setStyle("-fx-text-fill: darkgreen;");
                break;
        }
    }

    @FXML
    private void handleResetPassword() {
        String newPassword = newPasswordField.getText();
        String confirmPassword = confirmPasswordField.getText();

        // Validation
        if (newPassword.isEmpty() || confirmPassword.isEmpty()) {
            showAlert("Erreur", "Veuillez remplir tous les champs");
            return;
        }

        if (!newPassword.equals(confirmPassword)) {
            showAlert("Erreur", "Les mots de passe ne correspondent pas");
            return;
        }

        if (newPassword.length() < 6) {
            showAlert("Erreur", "Le mot de passe doit contenir au moins 6 caractères");
            return;
        }

        resetPasswordBtn.setDisable(true);
        if (loadingIndicator != null) loadingIndicator.setVisible(true);
        statusLabel.setText("⏳ Réinitialisation en cours...");

        new Thread(() -> {
            try {
                // Récupérer le token
                PasswordResetToken resetToken = tokenService.findByToken(token);

                if (resetToken == null || !resetToken.isValid()) {
                    javafx.application.Platform.runLater(() -> {
                        showAlert("Erreur", "Lien invalide ou expiré");
                        resetPasswordBtn.setDisable(false);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                    });
                    return;
                }

                // Récupérer l'utilisateur
                UserModel user = userService.getUserById(resetToken.getUserId());

                if (user == null) {
                    javafx.application.Platform.runLater(() -> {
                        showAlert("Erreur", "Utilisateur non trouvé");
                        resetPasswordBtn.setDisable(false);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                    });
                    return;
                }

                // Mettre à jour le mot de passe
                user.setPassword(newPassword);
                boolean updated = userService.updateUser(user);

                if (updated) {
                    // Marquer le token comme utilisé
                    tokenService.markTokenAsUsed(token);

                    javafx.application.Platform.runLater(() -> {
                        showAlert("Succès", "Mot de passe réinitialisé avec succès !");
                        redirectToLogin();
                    });
                } else {
                    javafx.application.Platform.runLater(() -> {
                        showAlert("Erreur", "Échec de la mise à jour du mot de passe");
                        resetPasswordBtn.setDisable(false);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                    });
                }
            } catch (Exception e) {
                e.printStackTrace();
                javafx.application.Platform.runLater(() -> {
                    showAlert("Erreur", "Une erreur est survenue: " + e.getMessage());
                    resetPasswordBtn.setDisable(false);
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });
            }
        }).start();
    }

    @FXML
    private void handleBackToLogin() {
        redirectToLogin();
    }

    private void redirectToLogin() {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Stage stage = (Stage) backToLoginBtn.getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}