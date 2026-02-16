package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class HelloApplication extends Application {

    @Override
    public void start(Stage stage) throws Exception {
        System.out.println("🚀 ========== DÉMARRAGE EVENTFLOW ==========");

        try {
            // Charger le FXML
            System.out.println("📂 Chargement main-layout.fxml...");
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/main-layout.fxml")
            );
            Parent root = loader.load();
            System.out.println("✅ main-layout.fxml chargé avec succès");

            // Créer la scène
            Scene scene = new Scene(root, 1400, 900);
            System.out.println("✅ Scene créée (1400x900)");

            // Charger le CSS
            System.out.println("🎨 Chargement CSS...");
            try {
                String css = getClass().getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm();
                scene.getStylesheets().add(css);
                System.out.println("✅ CSS chargé: " + css);
            } catch (Exception e) {
                System.err.println("⚠️ CSS introuvable: " + e.getMessage());
            }

            // Appliquer le thème AtlantaFX
            Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());
            System.out.println("✅ Thème AtlantaFX appliqué");

            // Configurer la fenêtre
            stage.setTitle("EventFlow - Gestion d'Événements");
            stage.setScene(scene);
            stage.setMaximized(true);
            stage.show();

            System.out.println("✅ ========== APPLICATION LANCÉE ==========");

        } catch (Exception e) {
            System.err.println("❌ ========== ERREUR CRITIQUE ==========");
            System.err.println("Message: " + e.getMessage());
            e.printStackTrace();
            throw e;
        }
    }

    public static void main(String[] args) {
        System.out.println("🔥 main() appelé - Lancement JavaFX...");
        launch();
    }
}