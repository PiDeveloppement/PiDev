package com.example.pidev.controller.auth;

import com.example.pidev.HelloApplication;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.UserService;
import com.example.pidev.utils.UserSession;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;

import java.io.IOException;
import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;
import java.util.prefs.Preferences;

public class LoginController implements Initializable {

    @FXML
    private TextField emailField;

    @FXML
    private PasswordField passwordField;

    @FXML
    private CheckBox rememberCheckbox;

    @FXML
    private Label loginMessageLabel;

    @FXML
    private Button submitButton;

    @FXML
    private Button cancelButton;

    @FXML
    private Hyperlink forgotPasswordLink;

    private UserService userService;
    private Preferences preferences;


    @Override
    public void initialize(URL location, ResourceBundle resources) {
        try {
            userService = new UserService();
            preferences = Preferences.userNodeForPackage(LoginController.class);
            System.out.println("✅ LoginController initialisé");

            // Charger les identifiants sauvegardés
            loadSavedCredentials();

        } catch (Exception e) {
            showAlert("Erreur", "Erreur de connexion à la base de données");
            e.printStackTrace();
        }
    }

    /**
     * Méthode appelée quand on clique sur "Se connecter"
     */
    @FXML
    private void loginButtonOnAction(ActionEvent event) {
        String email = emailField.getText().trim();
        String password = passwordField.getText().trim();

        // Validation des champs
        if (email.isEmpty()) {
            showMessage("Veuillez saisir votre email", "error");
            emailField.requestFocus();
            return;
        }

        if (password.isEmpty()) {
            showMessage("Veuillez saisir votre mot de passe", "error");
            passwordField.requestFocus();
            return;
        }

        try {
            System.out.println("🔐 Tentative de connexion pour: " + email);

            // Authentifier l'utilisateur
            UserModel user = userService.authenticate(email, password);

            if (user != null) {
                // Connexion réussie
                System.out.println("✅ Utilisateur authentifié: " + user.getFirst_Name() + " " + user.getLast_Name());

                // Sauvegarder l'email si "Se souvenir de moi" est coché
                if (rememberCheckbox.isSelected()) {
                    saveCredentials(email, password);
                } else {
                    clearSavedCredentials();
                }

                // Stocker l'utilisateur dans la session
                UserSession.getInstance().setCurrentUser(user);

                // Afficher les infos de session
                UserSession.getInstance().printSessionInfo();

                // Charger le dashboard
                HelloApplication.loadDashboard();

            } else {
                // Échec de connexion
                System.out.println("❌ Échec de connexion pour: " + email);
                showMessage("Email ou mot de passe incorrect", "error");
                passwordField.clear();
                passwordField.requestFocus();
            }

        } catch (Exception e) {
            e.printStackTrace();
            showMessage("Erreur de connexion: " + e.getMessage(), "error");
        }
    }

    /**
     * ✅ Méthode pour mot de passe oublié - CORRIGÉE
     */
    @FXML
    private void handleForgotPassword(ActionEvent event) {
        try {
            System.out.println("🔑 Redirection vers la page mot de passe oublié");

            // Vérifier plusieurs chemins possibles
            URL fxmlLocation = null;
            String[] possiblePaths = {
                    "/com/example/pidev/fxml/user/forgot_password.fxml",
                    "/fxml/auth/forgot_password.fxml",
                    "/auth/forgot_password.fxml",
                    "../fxml/auth/forgot_password.fxml"
            };

            for (String path : possiblePaths) {
                fxmlLocation = getClass().getResource(path);
                if (fxmlLocation != null) {
                    System.out.println("✅ FXML trouvé à: " + path);
                    break;
                }
            }

            if (fxmlLocation == null) {
                System.err.println("❌ FXML forgot_password.fxml introuvable!");
                showAlert("Erreur", "Fichier de récupération de mot de passe introuvable");
                return;
            }

            Parent root = FXMLLoader.load(fxmlLocation);
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Mot de passe oublié - EventFlow");
            stage.show();

        } catch (IOException e) {
            System.err.println("❌ Erreur lors de la redirection: " + e.getMessage());
            e.printStackTrace();
            showAlert("Erreur", "Impossible d'ouvrir la page de récupération de mot de passe");
        }
    }

    /**
     * ✅ Méthode pour charger les identifiants sauvegardés
     */
    private void loadSavedCredentials() {
        String savedEmail = preferences.get("saved_email", "");
        String savedPassword = preferences.get("saved_password", "");

        if (!savedEmail.isEmpty() && !savedPassword.isEmpty()) {
            emailField.setText(savedEmail);
            passwordField.setText(savedPassword);
            rememberCheckbox.setSelected(true);
            System.out.println("📧 Identifiants chargés pour: " + savedEmail);
        }
    }

    /**
     * ✅ Méthode pour sauvegarder les identifiants
     */
    private void saveCredentials(String email, String password) {
        preferences.put("saved_email", email);
        preferences.put("saved_password", password);
        System.out.println("💾 Identifiants sauvegardés");
    }

    /**
     * ✅ Méthode pour effacer les identifiants sauvegardés
     */
    private void clearSavedCredentials() {
        preferences.remove("saved_email");
        preferences.remove("saved_password");
        System.out.println("🗑️ Identifiants effacés");
    }

    /**
     * Méthode appelée quand on clique sur "Annuler"
     */
    @FXML
    private void cancelButtonOnAction(ActionEvent event) {
        clearFields();
        showMessage("Champs effacés", "info");
    }

    /**
     * ✅ Méthode appelée quand on clique sur "Créer un compte"
     */
    @FXML
    private void goToSignup(ActionEvent event) {
        try {
            System.out.println("📝 Redirection vers la page d'inscription");

            URL fxmlLocation = getClass().getResource("/com/example/pidev/fxml/auth/signup.fxml");
            if (fxmlLocation == null) {
                System.err.println("❌ FXML signup.fxml introuvable!");
                showAlert("Erreur", "Fichier d'inscription introuvable");
                return;
            }

            Parent root = FXMLLoader.load(fxmlLocation);
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();

        } catch (IOException e) {
            System.err.println("❌ Erreur lors de la redirection vers l'inscription");
            e.printStackTrace();
            showAlert("Erreur", "Impossible d'ouvrir la page d'inscription");
        }
    }

    /**
     * Affiche un message dans le label prévu
     */
    private void showMessage(String message, String type) {
        if (loginMessageLabel != null) {
            loginMessageLabel.setText(message);
            switch (type) {
                case "error":
                    loginMessageLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px;");
                    break;
                case "success":
                    loginMessageLabel.setStyle("-fx-text-fill: #10b981; -fx-font-size: 12px;");
                    break;
                default:
                    loginMessageLabel.setStyle("-fx-text-fill: #2196F3; -fx-font-size: 12px;");
            }
        }
    }

    /**
     * Efface les champs du formulaire
     */
    private void clearFields() {
        emailField.clear();
        passwordField.clear();
        if (loginMessageLabel != null) {
            loginMessageLabel.setText("");
        }
    }

    /**
     * Affiche une boîte de dialogue d'alerte
     */
    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    @FXML
    private void goToLanding(ActionEvent event) {
        HelloApplication.loadLandingPage();
    }
}