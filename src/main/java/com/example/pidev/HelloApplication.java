package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import com.example.pidev.controller.user.ResetPasswordController;
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

        // Charger la LANDING PAGE au démarrage
        Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/landingPage.fxml"));

        stage.initStyle(StageStyle.DECORATED);

        Scene scene = new Scene(root, 1400, 700);

        // CSS personnalisé
        scene.getStylesheets().add(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());

        stage.setTitle("EventFlow - Plateforme de gestion d'événements");
        stage.setScene(scene);
        stage.setMinWidth(1200);
        stage.setMinHeight(800);
        stage.show();

        // ✅ PLUS DE SERVEUR HTTP ! On le supprime complètement
        System.out.println("✅ Application démarrée - Mode WhatsApp sans serveur");
    }

    // ❌ SUPPRIMEZ COMPLÈTEMENT la méthode startRedirectServer()
    // private void startRedirectServer() { ... } à supprimer

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

            Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

            Stage newStage = new Stage();
            newStage.initStyle(StageStyle.DECORATED);

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

            primaryStage.close();
            primaryStage = newStage;
            primaryStage.show();
            primaryStage.centerOnScreen();

            System.out.println("✅ Dashboard chargé avec succès");

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement du dashboard");
            e.printStackTrace();
        }
    }

    public static void loadLandingPage() {
        try {
            System.out.println("📂 Chargement de la landing page");

            Parent root = FXMLLoader.load(
                    HelloApplication.class.getResource("/com/example/pidev/fxml/auth/landingPage.fxml")
            );

            Scene scene = new Scene(root, 1400, 900);
            scene.getStylesheets().add(
                    HelloApplication.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm()
            );

            primaryStage.setTitle("EventFlow - Plateforme de gestion d'événements");
            primaryStage.setScene(scene);
            primaryStage.setMinWidth(1200);
            primaryStage.setMinHeight(800);
            primaryStage.centerOnScreen();
            primaryStage.show();

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement de la landing page");
            e.printStackTrace();
        }
    }

    public static void loadLoginPage() {
        try {
            System.out.println("📂 Chargement de la page de connexion");

            Parent root = FXMLLoader.load(
                    HelloApplication.class.getResource("/com/example/pidev/fxml/auth/login.fxml")
            );

            Scene scene = new Scene(root, 1200, 800);
            scene.getStylesheets().add(
                    HelloApplication.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm()
            );

            primaryStage.setTitle("EventFlow - Connexion");
            primaryStage.setScene(scene);
            primaryStage.setMinWidth(1000);
            primaryStage.setMinHeight(700);
            primaryStage.centerOnScreen();
            primaryStage.show();

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement de la page de connexion");
            e.printStackTrace();
        }
    }

    public static void loadSignupPage() {
        try {
            System.out.println("📂 Chargement de la page d'inscription");

            Parent root = FXMLLoader.load(
                    HelloApplication.class.getResource("/com/example/pidev/fxml/auth/signup.fxml")
            );

            Scene scene = new Scene(root, 1200, 800);
            scene.getStylesheets().add(
                    HelloApplication.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm()
            );

            primaryStage.setTitle("EventFlow - Inscription");
            primaryStage.setScene(scene);
            primaryStage.setMinWidth(1000);
            primaryStage.setMinHeight(700);
            primaryStage.centerOnScreen();
            primaryStage.show();

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du chargement de la page d'inscription");
            e.printStackTrace();
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Ouvrir directement la fenêtre de réinitialisation
     * Appelée depuis ForgotPasswordController après envoi WhatsApp
     */
    public static void openResetPasswordWindow(String token) {
        javafx.application.Platform.runLater(() -> {
            try {
                System.out.println("🖥️ Ouverture de la fenêtre de réinitialisation");

                FXMLLoader loader = new FXMLLoader(
                        HelloApplication.class.getResource("/com/example/pidev/fxml/user/reset_password.fxml")
                );
                Parent root = loader.load();

                // ✅ Récupérer le contrôleur
                ResetPasswordController controller = loader.getController();

                // ✅ Passer le token (optionnel - peut être null)
                // Note: La méthode setToken n'existe plus dans le nouveau contrôleur
                // Si vous voulez pré-remplir le token, ajoutez cette méthode

                Stage resetStage = new Stage();
                resetStage.setTitle("Réinitialisation du mot de passe");
                resetStage.setScene(new Scene(root));
                resetStage.show();

            } catch (Exception e) {
                System.err.println("❌ Erreur ouverture fenêtre: " + e.getMessage());
                e.printStackTrace();
            }
        });
    }
}