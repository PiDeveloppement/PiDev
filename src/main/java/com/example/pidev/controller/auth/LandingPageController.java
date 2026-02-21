package com.example.pidev.controller.auth;

import com.example.pidev.HelloApplication;
import javafx.fxml.FXML;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.scene.web.WebView;
import javafx.scene.web.WebEngine;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.geometry.Pos;
import javafx.geometry.Insets;
import javafx.concurrent.Worker;

import java.net.URL;

public class LandingPageController {

    @FXML
    private ScrollPane mainScrollPane;

    // Références aux sections pour le scroll
    @FXML
    private VBox homeSection;
    @FXML
    private VBox featuresSection;
    @FXML
    private VBox contactSection;

    // ==================== MÉTHODES DE NAVIGATION ====================

    @FXML
    private void handleLogin() {
        System.out.println("📂 Redirection vers la page de connexion");
        HelloApplication.loadLoginPage();
    }

    @FXML
    private void handleSignup() {
        System.out.println("📂 Redirection vers la page d'inscription");
        HelloApplication.loadSignupPage();
    }

    // ==================== MÉTHODES DE SCROLL ====================

    @FXML
    private void scrollToTop() {
        if (mainScrollPane != null) {
            mainScrollPane.setVvalue(0);
            System.out.println("⬆️ Scroll vers le haut");
        }
    }

    @FXML
    private void scrollToFeatures() {
        if (mainScrollPane != null) {
            mainScrollPane.setVvalue(0.35);
            System.out.println("📋 Scroll vers la section fonctionnalités");
        }
    }

    @FXML
    private void scrollToContact() {
        if (mainScrollPane != null) {
            mainScrollPane.setVvalue(0.85);
            System.out.println("📞 Scroll vers la section contact");
        }
    }

    // ==================== MÉTHODES D'ANIMATION DES CARTES ====================

    @FXML
    private void animateCard(javafx.scene.input.MouseEvent event) {
        StackPane card = (StackPane) event.getSource();
        card.setStyle("-fx-background-color: #f8fafc; -fx-background-radius: 20; -fx-padding: 30; " +
                "-fx-effect: dropshadow(gaussian, rgba(13,71,161,0.3), 20, 0, 0, 10); " +
                "-fx-cursor: hand; -fx-scale-x: 1.02; -fx-scale-y: 1.02; " +
                "-fx-transition: all 0.3s ease-in-out;");
    }

    @FXML
    private void resetCard(javafx.scene.input.MouseEvent event) {
        StackPane card = (StackPane) event.getSource();
        card.setStyle("-fx-background-color: #f8fafc; -fx-background-radius: 20; -fx-padding: 30; " +
                "-fx-cursor: hand; -fx-effect: null; -fx-scale-x: 1; -fx-scale-y: 1;");
    }

    // ==================== MÉTHODE PRINCIPALE POUR LA DÉMO ====================

    @FXML
    private void handleDemo() {
        System.out.println("▶️ Ouverture de la vidéo de démonstration...");
        // Appel de la méthode avec le chemin de votre vidéo
        playVideo("/com/example/pidev/videos/Média1.mp4");
    }

    /**
     * Méthode générique pour lire une vidéo à partir d'un chemin
     * @param videoPath Le chemin de la vidéo dans les ressources
     */
    private void playVideo(String videoPath) {
        try {
            // Vérifier si la vidéo existe
            URL videoUrl = getClass().getResource(videoPath);
            if (videoUrl == null) {
                showAlert("Erreur", "Vidéo non trouvée: " + videoPath);
                return;
            }

            // Créer une nouvelle fenêtre modale
            Stage videoStage = new Stage();
            videoStage.setTitle("EventFlow - Vidéo de démonstration");
            videoStage.initModality(Modality.APPLICATION_MODAL);
            videoStage.setWidth(1000);
            videoStage.setHeight(650);
            videoStage.setResizable(false);

            // Layout principal
            VBox mainContainer = new VBox(20);
            mainContainer.setStyle("-fx-background-color: #0A1929; -fx-padding: 30;");
            mainContainer.setAlignment(Pos.TOP_CENTER);

            // ==================== EN-TÊTE ====================
            HBox headerBox = createVideoHeader(videoStage);

            // ==================== CONTENEUR VIDÉO ====================
            VBox videoContainer = createVideoPlayer(videoUrl.toExternalForm());

            // ==================== INFORMATIONS SOUS LA VIDÉO ====================
            VBox infoBox = createVideoInfoBox();

            // ==================== BOUTONS D'ACTION ====================
            HBox actionBox = createVideoActionBox(videoStage, videoPath);

            // Assemblage
            mainContainer.getChildren().addAll(headerBox, videoContainer, infoBox, actionBox);

            // Scene et affichage
            Scene scene = new Scene(mainContainer);
            videoStage.setScene(scene);
            videoStage.show();

        } catch (Exception e) {
            System.err.println("❌ Erreur lors de la lecture de la vidéo: " + e.getMessage());
            e.printStackTrace();
            showAlert("Erreur", "Impossible de lire la vidéo: " + e.getMessage());
        }
    }

    /**
     * Crée le lecteur vidéo
     */
    private VBox createVideoPlayer(String videoUrl) {
        VBox videoContainer = new VBox();
        videoContainer.setStyle("-fx-background-color: #000000; -fx-background-radius: 12; " +
                "-fx-border-color: #3b82f6; -fx-border-width: 2; -fx-border-radius: 12;");
        videoContainer.setPrefHeight(400);
        videoContainer.setAlignment(Pos.CENTER);

        // Indicateur de chargement
        ProgressIndicator loadingIndicator = new ProgressIndicator();
        loadingIndicator.setStyle("-fx-progress-color: #3b82f6;");
        loadingIndicator.setVisible(true);
        loadingIndicator.setMaxSize(60, 60);

        // WebView pour la vidéo
        WebView webView = new WebView();
        WebEngine webEngine = webView.getEngine();
        webView.setPrefHeight(400);
        webView.setPrefWidth(900);
        webView.setVisible(false);

        // Créer le HTML avec la vidéo
        String htmlContent = createVideoHTML(videoUrl);

        // Gestionnaire de chargement
        webEngine.getLoadWorker().stateProperty().addListener((obs, oldState, newState) -> {
            if (newState == Worker.State.RUNNING) {
                loadingIndicator.setVisible(true);
                webView.setVisible(false);
            } else if (newState == Worker.State.SUCCEEDED) {
                loadingIndicator.setVisible(false);
                webView.setVisible(true);
                System.out.println("✅ Vidéo chargée avec succès");
            } else if (newState == Worker.State.FAILED) {
                loadingIndicator.setVisible(false);
                webView.setVisible(true);
                // Afficher un message d'erreur dans la WebView
                String errorHtml = "<html><body style='background:black; color:white; display:flex; justify-content:center; align-items:center; height:100%;'>" +
                        "<h2>❌ Erreur de chargement de la vidéo</h2></body></html>";
                webEngine.loadContent(errorHtml);
                System.out.println("❌ Échec du chargement de la vidéo");
            }
        });

        // Charger la vidéo
        webEngine.loadContent(htmlContent);

        videoContainer.getChildren().addAll(loadingIndicator, webView);
        StackPane.setAlignment(loadingIndicator, Pos.CENTER);

        return videoContainer;
    }

    /**
     * Crée l'en-tête de la fenêtre vidéo
     */
    private HBox createVideoHeader(Stage videoStage) {
        HBox headerBox = new HBox(15);
        headerBox.setAlignment(Pos.CENTER_LEFT);
        headerBox.setPadding(new Insets(0, 0, 20, 0));

        Label titleLabel = new Label("🎬 Démonstration EventFlow");
        titleLabel.setStyle("-fx-font-size: 24px; -fx-font-weight: bold; -fx-text-fill: white;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button closeBtn = new Button("✕");
        closeBtn.setStyle("-fx-background-color: transparent; -fx-text-fill: white; -fx-font-size: 24px; " +
                "-fx-cursor: hand; -fx-padding: 5 10; -fx-font-weight: bold;");
        closeBtn.setOnAction(e -> videoStage.close());

        // Effet hover pour le bouton fermer
        closeBtn.setOnMouseEntered(e ->
                closeBtn.setStyle("-fx-background-color: #ef4444; -fx-text-fill: white; -fx-font-size: 24px; " +
                        "-fx-cursor: hand; -fx-padding: 5 10; -fx-font-weight: bold; -fx-background-radius: 5;"));
        closeBtn.setOnMouseExited(e ->
                closeBtn.setStyle("-fx-background-color: transparent; -fx-text-fill: white; -fx-font-size: 24px; " +
                        "-fx-cursor: hand; -fx-padding: 5 10; -fx-font-weight: bold;"));

        headerBox.getChildren().addAll(titleLabel, spacer, closeBtn);
        return headerBox;
    }

    /**
     * Crée le code HTML pour la vidéo
     */
    private String createVideoHTML(String videoUrl) {
        return String.format("""
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body, html { 
                        margin: 0; 
                        padding: 0; 
                        width: 100%%; 
                        height: 100%%; 
                        overflow: hidden; 
                        background: #000000;
                    }
                    video { 
                        width: 100%%; 
                        height: 100%%; 
                        object-fit: contain;
                        background: #000000;
                    }
                </style>
            </head>
            <body>
                <video controls autoplay>
                    <source src="%s" type="video/mp4">
                    Votre navigateur ne supporte pas la lecture de vidéos.
                </video>
            </body>
            </html>
            """, videoUrl);
    }

    /**
     * Crée la boîte d'informations sous la vidéo
     */
    private VBox createVideoInfoBox() {
        VBox infoBox = new VBox(10);
        infoBox.setAlignment(Pos.CENTER_LEFT);
        infoBox.setPadding(new Insets(20, 0, 0, 0));

        Label infoTitle = new Label("✨ Découvrez EventFlow en action");
        infoTitle.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: white;");

        Label infoText = new Label(
                "Cette démonstration vous montre comment :\n" +
                        "✓ Créer et gérer vos événements en quelques clics\n" +
                        "✓ Ajouter des participants et suivre leurs inscriptions\n" +
                        "✓ Gérer vos sponsors et leurs contrats\n" +
                        "✓ Visualiser les statistiques en temps réel\n" +
                        "✓ Générer des rapports détaillés"
        );
        infoText.setStyle("-fx-text-fill: #94a3b8; -fx-font-size: 14px; -fx-line-spacing: 5;");
        infoText.setWrapText(true);

        infoBox.getChildren().addAll(infoTitle, infoText);
        return infoBox;
    }

    /**
     * Crée la boîte des boutons d'action (SANS le bouton "Créer un compte gratuit")
     */
    private HBox createVideoActionBox(Stage videoStage, String videoPath) {
        HBox actionBox = new HBox(15);
        actionBox.setAlignment(Pos.CENTER_RIGHT);
        actionBox.setPadding(new Insets(20, 0, 0, 0));

        Button replayBtn = new Button("🔄 Revoir la démo");
        replayBtn.setStyle("-fx-background-color: #3b82f6; -fx-text-fill: white; -fx-font-weight: bold; " +
                "-fx-padding: 12 25; -fx-background-radius: 8; -fx-cursor: hand; " +
                "-fx-border-color: #3b82f6; -fx-border-width: 1.5; -fx-font-size: 14px;");

        // Effet hover pour le bouton Revoir
        replayBtn.setOnMouseEntered(e ->
                replayBtn.setStyle("-fx-background-color: #2563eb; -fx-text-fill: white; -fx-font-weight: bold; " +
                        "-fx-padding: 12 25; -fx-background-radius: 8; -fx-cursor: hand; " +
                        "-fx-border-color: #2563eb; -fx-border-width: 1.5; -fx-font-size: 14px; " +
                        "-fx-effect: dropshadow(gaussian, #3b82f6, 10, 0, 0, 0);"));
        replayBtn.setOnMouseExited(e ->
                replayBtn.setStyle("-fx-background-color: #3b82f6; -fx-text-fill: white; -fx-font-weight: bold; " +
                        "-fx-padding: 12 25; -fx-background-radius: 8; -fx-cursor: hand; " +
                        "-fx-border-color: #3b82f6; -fx-border-width: 1.5; -fx-font-size: 14px;"));

        replayBtn.setOnAction(e -> {
            videoStage.close();
            playVideo(videoPath); // Rejouer la même vidéo
        });

        actionBox.getChildren().addAll(replayBtn);
        return actionBox;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}