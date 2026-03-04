package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.net.URL;
import java.util.Objects;

public class HelloApplication extends Application {

    private static Stage primaryStage;

    public static Stage getPrimaryStage() {
        return primaryStage;
    }

    @Override
    public void start(Stage stage) throws Exception {
        primaryStage = stage;
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        // Charger la landing page (page d'accueil) au lieu du dashboard
        URL fxml = getClass().getResource("/com/example/pidev/fxml/auth/landingPage.fxml");
        if (fxml == null) {
            System.err.println("❌ FXML landingPage.fxml introuvable !");
            return;
        }
        Parent root = FXMLLoader.load(fxml);
        Scene scene = new Scene(root, 1200, 800);
        scene.getStylesheets().add(
                Objects.requireNonNull(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css")).toExternalForm()
        );
        stage.setTitle("EventFlow - Accueil");
        stage.setScene(scene);
        stage.setMinWidth(1000);
        stage.setMinHeight(700);
        stage.show();
    }

    // Méthodes de navigation (pour les redirections depuis les contrôleurs)
    public static void loadLoginPage() {
        loadPage("/com/example/pidev/fxml/auth/login.fxml");
    }

    public static void loadSignupPage() {
        loadPage("/com/example/pidev/fxml/auth/signup.fxml");
    }

    public static void loadLandingPage() {
        loadPage("/com/example/pidev/fxml/auth/landingPage.fxml");
    }

    public static void loadDashboard() {
        loadPage("/com/example/pidev/fxml/MainLayout.fxml");
    }

    private static void loadPage(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(HelloApplication.class.getResource(fxmlPath));
            Parent root = loader.load();
            primaryStage.getScene().setRoot(root);
            // Mettre à jour le titre de la fenêtre en fonction de la page
            if (fxmlPath.contains("login")) {
                primaryStage.setTitle("EventFlow - Connexion");
            } else if (fxmlPath.contains("signup")) {
                primaryStage.setTitle("EventFlow - Inscription");
            } else if (fxmlPath.contains("landingPage")) {
                primaryStage.setTitle("EventFlow - Accueil");
            } else {
                primaryStage.setTitle("EventFlow - Dashboard");
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement de " + fxmlPath + " : " + e.getMessage());
            e.printStackTrace();
        }
    }

    public static void main(String[] args) {
        launch(args);
    }
}