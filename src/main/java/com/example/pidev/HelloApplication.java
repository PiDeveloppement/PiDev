package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import com.example.pidev.controller.user.ResetPasswordController;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

// Imports pour le serveur HTTP
import com.sun.net.httpserver.HttpServer;
import com.sun.net.httpserver.HttpExchange;
import java.net.InetSocketAddress;
import java.io.OutputStream;

public class HelloApplication extends Application {

    private static Stage primaryStage;

    @Override
    public void start(Stage stage) throws Exception {
        primaryStage = stage;

        // ✅ DÉMARRER LE SERVEUR DE REDIRECTION (port 8085)
        startRedirectServer();

        // Appliquer AtlantaFX
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        // Charger la LANDING PAGE au démarrage
        Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/landingPage.fxml"));

        stage.initStyle(StageStyle.DECORATED);

        Scene scene = new Scene(root, 1400, 700);

        // Votre CSS personnalisé
        scene.getStylesheets().add(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());

        stage.setTitle("EventFlow - Plateforme de gestion d'événements");
        stage.setScene(scene);
        stage.setMinWidth(1200);
        stage.setMinHeight(800);
        stage.show();
    }

    // ✅ SERVEUR DE REDIRECTION COMPLET
    private void startRedirectServer() {
        System.out.println("🔵 [1] Entrée dans startRedirectServer()");

        new Thread(() -> {
            System.out.println("🟡 [2] Thread serveur démarré");

            try {
                System.out.println("🟢 [3] Tentative de création du serveur sur le port 8085...");
                HttpServer server = HttpServer.create(new InetSocketAddress(8085), 0);
                System.out.println("✅ [4] Serveur créé avec succès !");

                server.createContext("/reset", exchange -> {
                    System.out.println("📨 [5] Requête reçue sur /reset !");

                    // Extraire le token de l'URL
                    String query = exchange.getRequestURI().getQuery();
                    String token = null;
                    if (query != null && query.startsWith("token=")) {
                        token = query.substring(6);
                    }
                    System.out.println("🔑 Token extrait: " + token);

                    // Créer une réponse HTML complète
                    String response = "<!DOCTYPE html>\n" +
                            "<html>\n" +
                            "<head>\n" +
                            "    <meta charset='UTF-8'>\n" +
                            "    <title>Réinitialisation</title>\n" +
                            "    <style>\n" +
                            "        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }\n" +
                            "        .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }\n" +
                            "        .success { color: #4CAF50; font-size: 48px; margin-bottom: 20px; }\n" +
                            "        h2 { color: #333; margin-bottom: 20px; }\n" +
                            "        p { color: #666; margin-bottom: 30px; }\n" +
                            "        .token { background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; color: #333; }\n" +
                            "    </style>\n" +
                            "</head>\n" +
                            "<body>\n" +
                            "    <div class='card'>\n" +
                            "        <div class='success'>✅</div>\n" +
                            "        <h2>Réinitialisation en cours</h2>\n" +
                            "        <p>La fenêtre de réinitialisation va s'ouvrir dans l'application.</p>\n" +
                            "        <div class='token'>" + (token != null ? token : "aucun token") + "</div>\n" +
                            "        <p style='margin-top: 30px; font-size: 12px; color: #999;'>Cette page se fermera automatiquement dans 3 secondes.</p>\n" +
                            "    </div>\n" +
                            "    <script>\n" +
                            "        setTimeout(() => window.close(), 3000);\n" +
                            "    </script>\n" +
                            "</body>\n" +
                            "</html>";

                    // Configurer les en-têtes
                    exchange.getResponseHeaders().set("Content-Type", "text/html; charset=UTF-8");
                    exchange.getResponseHeaders().set("Cache-Control", "no-cache");

                    // Envoyer la réponse
                    byte[] responseBytes = response.getBytes("UTF-8");
                    exchange.sendResponseHeaders(200, responseBytes.length);
                    OutputStream os = exchange.getResponseBody();
                    os.write(responseBytes);
                    os.close();

                    // Ouvrir la fenêtre JavaFX
                    if (token != null) {
                        final String finalToken = token;
                        javafx.application.Platform.runLater(() -> {
                            try {
                                System.out.println("🖥️ Ouverture de la fenêtre de réinitialisation");
                                FXMLLoader loader = new FXMLLoader(
                                        getClass().getResource("/com/example/pidev/fxml/user/reset_password.fxml")
                                );
                                Stage resetStage = new Stage();
                                resetStage.setScene(new Scene(loader.load()));

                                ResetPasswordController controller = loader.getController();
                                controller.setToken(finalToken);

                                resetStage.setTitle("Réinitialisation du mot de passe");
                                resetStage.show();
                            } catch (Exception e) {
                                System.err.println("❌ Erreur ouverture fenêtre: " + e.getMessage());
                                e.printStackTrace();
                            }
                        });
                    }
                });

                server.start();
                System.out.println("🎉 [6] SERVEUR DÉMARRÉ sur http://localhost:8085");
                System.out.println("🔗 Lien de test: http://localhost:8085/reset?token=test123");

            } catch (Exception e) {
                System.err.println("💥 [ERREUR] " + e.getMessage());
                e.printStackTrace();
            }
        }).start();

        System.out.println("🔵 [7] Sortie de startRedirectServer()");
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

            System.out.println("🔍 After - New stage style: " + primaryStage.getStyle());
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
}