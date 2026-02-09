package com.example.pidev.controller.auth;

import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.user.LoginService;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

import java.io.IOException;

public class LoginController {

    @FXML
    private TextField emailField;
    @FXML
    private PasswordField passwordField;
    @FXML
    private Button submitButton;
    @FXML
    private Button cancelButton;
    @FXML
    private Label loginMessageLabel;

    private LoginService loginService;

    public LoginController() {
        try {
            loginService = new LoginService();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void loginButtonOnAction(ActionEvent event) throws IOException {
        String email = emailField.getText();
        String password = passwordField.getText();

        UserModel user = loginService.authenticate(email, password);

        if (user != null) {
            loginMessageLabel.setText("Login successful! Role: " + user.getRole_Id());
            openDashboard(event);
        } else {
            loginMessageLabel.setText("Invalid Login. Please try again.");
           

    }}

    private void openDashboard(ActionEvent event) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/example/pidev/fxml/dashboard/dashboard.fxml"));
            Parent root = loader.load();

            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow(); // récupère le stage actuel
            stage.setScene(new Scene(root));
            stage.setTitle("Dashboard");
            stage.setResizable(true); // autorise le glissement et le redimensionnement
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
    private void reloadLogin(ActionEvent event) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Parent root = loader.load();

            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.setResizable(true);
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }}

    @FXML
    public void cancelButtonOnAction(ActionEvent event) {
        Stage stage = (Stage) cancelButton.getScene().getWindow();
        stage.close();
    }

    // 🔹 Methode pour aller vers la page Signup
    @FXML
    private void goToSignup(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/signup.fxml"));
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Sign Up");
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
