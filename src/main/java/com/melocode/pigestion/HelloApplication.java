package com.melocode.pigestion;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

import java.io.IOException;

public class HelloApplication extends Application {

    private static Stage primaryStage;

    @Override
    public void start(Stage stage) throws IOException {
        primaryStage = stage;

        // 1. Appliquer le thème AtlantaFX (PrimerLight)
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        // 2. Charger le Main Layout (Chemin corrigé pour ton projet)
        FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("/com/melocode/pigestion/fxml/main_layout.fxml"));
        Parent root = fxmlLoader.load();

        // 3. Configurer la scène avec ton CSS personnalisé
        Scene scene = new Scene(root, 1400, 900);

        // Correction du path CSS selon ton arborescence
        String cssPath = getClass().getResource("/com/melocode/pigestion/css/atlantafx-custom.css").toExternalForm();
        scene.getStylesheets().add(cssPath);

        // 4. Configurer le Stage
        stage.initStyle(StageStyle.DECORATED);
        stage.setTitle("PI-GESTION v3.0 - EventFlow");
        stage.setScene(scene);

        // Définir des tailles minimales pour éviter que l'UI ne se casse
        stage.setMinWidth(1200);
        stage.setMinHeight(800);

        stage.show();
        stage.centerOnScreen();
    }

    // Méthode statique pour accéder au Stage depuis n'importe quel contrôleur
    public static Stage getPrimaryStage() {
        return primaryStage;
    }

    // Méthode pour recharger le layout principal si besoin (ex: après une connexion)
    public static void loadMainLayout() {
        try {
            FXMLLoader loader = new FXMLLoader(HelloApplication.class.getResource("/com/melocode/pigestion/fxml/main_layout.fxml"));
            Parent root = loader.load();

            Scene scene = new Scene(root, 1400, 900);
            scene.getStylesheets().add(HelloApplication.class.getResource("/com/melocode/pigestion/css/atlantafx-custom.css").toExternalForm());

            primaryStage.setTitle("PI-GESTION - Dashboard");
            primaryStage.setScene(scene);
            primaryStage.centerOnScreen();
        } catch (Exception e) {
            System.err.println("Erreur lors du rechargement du layout principal");
            e.printStackTrace();
        }
    }

    public static void main(String[] args) {
        launch();
    }
}