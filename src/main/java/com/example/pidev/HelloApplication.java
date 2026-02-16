package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

public class HelloApplication extends Application {

    private static Stage primaryStage;

    @Override
    public void start(Stage stage) throws Exception {
        primaryStage = stage;

        // Appliquer AtlantaFX
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));

        stage.initStyle(StageStyle.DECORATED);

        Scene scene = new Scene(root, 1200, 800);

        // Votre CSS personnalisé
        scene.getStylesheets().add(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());

        stage.setTitle("EventFlow - Connexion");
        stage.setScene(scene);
        stage.setMinWidth(1000);
        stage.setMinHeight(700);
        stage.show();
    }

    public static Stage getPrimaryStage() {
        return primaryStage;
    }

    public static void loadMainLayout() {
        try {
            FXMLLoader loader = new FXMLLoader(HelloApplication.class.getResource("/com/example/pidev/fxml/main_layout.fxml"));
            Parent root = loader.load();

            MainController mainController = loader.getController();
            mainController.loadPage("/com/example/pidev/fxml/dashboard/dashboard.fxml");

            Scene scene = new Scene(root, 1400, 900);

            // Votre CSS personnalisé
            scene.getStylesheets().add(HelloApplication.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());

            primaryStage.setTitle("EventFlow - Dashboard");
            primaryStage.setScene(scene);
            primaryStage.setMinWidth(1200);
            primaryStage.setMinHeight(800);
            primaryStage.setResizable(true);
            primaryStage.centerOnScreen();

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
    public static void loadDashboard() {
        try {
            System.out.println("Chargement du dashboard...");
            System.out.println("🔍 Before - Stage style: " + primaryStage.getStyle());

            // Réappliquer le thème
            Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

            // FORCE the stage style to DECORATED again (this might not work if stage is visible)
            // But let's try to recreate the stage
            Stage newStage = new Stage();
            newStage.initStyle(StageStyle.DECORATED);

            // Charger le main layout
            FXMLLoader loader = new FXMLLoader(
                    HelloApplication.class.getResource("/com/example/pidev/fxml/main_layout.fxml")
            );
            Parent root = loader.load();

            Scene scene = new Scene(root, 1400, 900);
            scene.getStylesheets().add(
                    HelloApplication.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm()
            );

            newStage.setScene(scene);
            newStage.setTitle("EventFlow - Dashboard");
            newStage.setMinWidth(1200);
            newStage.setMinHeight(800);

            // Close old stage and set new one
            primaryStage.close();
            primaryStage = newStage;
            primaryStage.show();
            primaryStage.centerOnScreen();

            System.out.println("🔍 After - New stage style: " + primaryStage.getStyle());
            System.out.println("✅ Dashboard chargé avec succès");

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement du dashboard");
            e.printStackTrace();
        }
    }
    public static void main(String[] args) {
        launch(args);
    }
}
