package com.example.pidev.controller.auth;

import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.UserService;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.stage.Stage;

import java.io.IOException;
import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class SignupController implements Initializable {

    @FXML private TextField Id_User;
    @FXML private TextField Password;
    @FXML private TextField ConfirmerPassword;
    @FXML private TextField First_Name;
    @FXML private TextField Last_Name;
    @FXML private TextField Email;
    @FXML private TextField Faculte;
    @FXML private Label ConfirmerPasswordLabel;
    @FXML private Label RegistrationMessageLabel;
    @FXML private Label emailLabel;
    @FXML private Button closeButton;

    private UserService userService;

    public SignupController() {
        // Initialise userService dans initialize
    }

    @Override
    public void initialize(URL url, ResourceBundle resourceBundle) {
        try {
            userService = new UserService();
        } catch (SQLException e) {
            e.printStackTrace();
        }

        // Validation visuelle de l'email
        Email.focusedProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal) { // quand on quitte le champ
                if (!isValidEmail(Email.getText())) {
                    emailLabel.setText("Email invalide (ex: test@gmail.com)");
                    Email.setStyle("-fx-border-color:red;");
                } else {
                    emailLabel.setText("");
                    Email.setStyle("");
                }
            }
        });

    }

    // Validation email utilitaire
    private boolean isValidEmail(String email) {
        if (email == null) return false;
        return email.matches("^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+$");
    }

    // Bouton d'inscription
    @FXML
    public void registerButtonOnAction(ActionEvent event) throws IOException {

        String email = Email.getText();
        String password = Password.getText();
        String confirmPassword = ConfirmerPassword.getText();

        // 1️⃣ Vérification email
        if (!isValidEmail(email)) {
            emailLabel.setText("Email invalide !");
            Email.setStyle("-fx-border-color:red;");
            return;
        }

        // 2️⃣ Vérification mot de passe
        if (!password.equals(confirmPassword)) {
            ConfirmerPasswordLabel.setText("Passwords do not match!");
            return;
        }

        // 3️⃣ Création de l'utilisateur
        UserModel user = new UserModel(
                First_Name.getText(),
                Last_Name.getText(),
                Email.getText(),
                Faculte.getText(),
                Password.getText(),
                1
        );

        // 4️⃣ Enregistrement via UserService
        boolean success = userService.registerUser(user);

        if (success) {
            RegistrationMessageLabel.setText("User has been registered successfully!");

            // Redirection vers Login
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();
        } else {
            RegistrationMessageLabel.setText("Registration failed! Try again.");
        }
    }

    // Bouton fermeture
    @FXML
    public void closeButtonOnAction(ActionEvent event){
        Stage stage = (Stage) closeButton.getScene().getWindow();
        stage.close();
        Platform.exit();
    }

    // Redirection vers login
    @FXML
    private void goToLogin(ActionEvent event) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Parent root = loader.load();

            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();

        } catch (IOException e) {
            e.printStackTrace();
        }
    }
}



