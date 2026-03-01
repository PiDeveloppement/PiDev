package com.example.pidev.controller.user;

import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.service.user.PasswordResetService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

public class ResetPasswordController {

    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;
    @FXML private Button resetPasswordBtn;
    @FXML private Button backToLoginBtn;
    @FXML private Label statusLabel;
    @FXML private Label tokenInfoLabel;
    @FXML private ProgressIndicator loadingIndicator;
    @FXML private Label passwordStrengthLabel;
    @FXML private VBox formBox; // Le conteneur des champs

    private PasswordResetService resetService;
    private String token;
    private PasswordResetToken resetToken;

    @FXML
    public void initialize() {
        resetService = new PasswordResetService();

        if (loadingIndicator != null) {
            loadingIndicator.setVisible(false);
        }

        // Cacher le formulaire au départ
        if (formBox != null) {
            formBox.setVisible(false);
            formBox.setManaged(false);
        }

        // Validation en temps réel
        setupValidators();
    }

    private void setupValidators() {
        if (newPasswordField != null) {
            newPasswordField.textProperty().addListener((obs, oldVal, newVal) -> {
                updatePasswordStrength(newVal);
            });
        }

        if (confirmPasswordField != null) {
            confirmPasswordField.textProperty().addListener((obs, oldVal, newVal) -> {
                checkPasswordsMatch();
            });
        }
    }

    public void setToken(String token) {
        this.token = token;
        validateToken();
    }

    private void validateToken() {
        if (loadingIndicator != null) loadingIndicator.setVisible(true);
        tokenInfoLabel.setText("Validation du token en cours...");

        new Thread(() -> {
            try {
                if (token == null || token.isEmpty()) {
                    javafx.application.Platform.runLater(() -> {
                        tokenInfoLabel.setText("❌ Lien invalide");
                        tokenInfoLabel.setStyle("-fx-text-fill: red; -fx-font-weight: bold;");
                        resetPasswordBtn.setDisable(true);
                        if (loadingIndicator != null) loadingIndicator.setVisible(false);
                    });
                    return;
                }

                resetToken = resetService.validateToken(token);

                javafx.application.Platform.runLater(() -> {
                    if (resetToken == null) {
                        tokenInfoLabel.setText("❌ Lien expiré ou déjà utilisé");
                        tokenInfoLabel.setStyle("-fx-text-fill: red; -fx-font-weight: bold;");
                        resetPasswordBtn.setDisable(true);

                        // Cacher le formulaire
                        if (formBox != null) {
                            formBox.setVisible(false);
                            formBox.setManaged(false);
                        }
                    } else {
                        tokenInfoLabel.setText("✅ Lien valide - Vous pouvez réinitialiser votre mot de passe");
                        tokenInfoLabel.setStyle("-fx-text-fill: green; -fx-font-weight: bold;");
                        resetPasswordBtn.setDisable(false);

                        // ✅ AFFICHER LE FORMULAIRE
                        if (formBox != null) {
                            formBox.setVisible(true);
                            formBox.setManaged(true);
                        }
                    }
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });

            } catch (Exception e) {
                javafx.application.Platform.runLater(() -> {
                    tokenInfoLabel.setText("❌ Erreur de validation");
                    tokenInfoLabel.setStyle("-fx-text-fill: red;");
                    resetPasswordBtn.setDisable(true);
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });
            }
        }).start();
    }

    @FXML
    private void handleResetPassword() {
        String newPassword = newPasswordField.getText();
        String confirmPassword = confirmPasswordField.getText();

        if (newPassword.isEmpty() || confirmPassword.isEmpty()) {
            showStatus("❌ Veuillez remplir tous les champs", "red");
            return;
        }

        if (!newPassword.equals(confirmPassword)) {
            showStatus("❌ Les mots de passe ne correspondent pas", "red");
            return;
        }

        String passwordError = validatePassword(newPassword);
        if (passwordError != null) {
            showStatus("❌ " + passwordError, "red");
            return;
        }

        resetPasswordBtn.setDisable(true);
        if (loadingIndicator != null) loadingIndicator.setVisible(true);

        new Thread(() -> {
            try {
                boolean updated = resetService.updatePassword(resetToken.getUserId(), newPassword);

                javafx.application.Platform.runLater(() -> {
                    if (updated) {
                        resetService.markTokenAsUsed(token);
                        showStatus("✅ Mot de passe mis à jour avec succès!", "green");

                        // Rediriger vers login après 2 secondes
                        new Thread(() -> {
                            try {
                                Thread.sleep(2000);
                                javafx.application.Platform.runLater(() -> {
                                    try {
                                        goToLogin();
                                    } catch (Exception e) {
                                        e.printStackTrace();
                                    }
                                });
                            } catch (InterruptedException e) {
                                e.printStackTrace();
                            }
                        }).start();

                    } else {
                        showStatus("❌ Erreur lors de la mise à jour", "red");
                        resetPasswordBtn.setDisable(false);
                    }
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });

            } catch (Exception e) {
                javafx.application.Platform.runLater(() -> {
                    showStatus("❌ Erreur: " + e.getMessage(), "red");
                    resetPasswordBtn.setDisable(false);
                    if (loadingIndicator != null) loadingIndicator.setVisible(false);
                });
            }
        }).start();
    }

    private void goToLogin() {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Stage stage = (Stage) backToLoginBtn.getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    private void handleBackToLogin() {
        goToLogin();
    }

    private void showStatus(String message, String color) {
        statusLabel.setText(message);
        statusLabel.setStyle("-fx-text-fill: " + color + "; -fx-font-weight: bold;");
    }

    private String validatePassword(String password) {
        if (password.length() < 6) {
            return "Le mot de passe doit contenir au moins 6 caractères";
        }
        if (!password.matches(".*[A-Z].*")) {
            return "Le mot de passe doit contenir au moins une majuscule";
        }
        if (!password.matches(".*[a-z].*")) {
            return "Le mot de passe doit contenir au moins une minuscule";
        }
        if (!password.matches(".*\\d.*")) {
            return "Le mot de passe doit contenir au moins un chiffre";
        }
        return null;
    }

    private void updatePasswordStrength(String password) {
        if (passwordStrengthLabel == null) return;

        if (password == null || password.isEmpty()) {
            passwordStrengthLabel.setText("");
            return;
        }

        int strength = 0;
        if (password.length() >= 6) strength++;
        if (password.matches(".*[A-Z].*")) strength++;
        if (password.matches(".*[a-z].*")) strength++;
        if (password.matches(".*\\d.*")) strength++;

        String text;
        String style;

        switch (strength) {
            case 0:
            case 1:
                text = "🔴 Faible";
                style = "-fx-text-fill: red;";
                break;
            case 2:
            case 3:
                text = "🟡 Moyen";
                style = "-fx-text-fill: orange;";
                break;
            default:
                text = "🟢 Fort";
                style = "-fx-text-fill: green;";
        }

        passwordStrengthLabel.setText(text);
        passwordStrengthLabel.setStyle(style);
    }

    private void checkPasswordsMatch() {
        if (confirmPasswordField == null || newPasswordField == null) return;

        String newPass = newPasswordField.getText();
        String confirmPass = confirmPasswordField.getText();

        if (!confirmPass.isEmpty()) {
            if (newPass.equals(confirmPass)) {
                confirmPasswordField.setStyle("-fx-border-color: green; -fx-border-width: 2;");
            } else {
                confirmPasswordField.setStyle("-fx-border-color: red; -fx-border-width: 2;");
            }
        } else {
            confirmPasswordField.setStyle("");
        }
    }
}