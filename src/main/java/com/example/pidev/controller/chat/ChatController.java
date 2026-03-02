package com.example.pidev.controller.chat;

import com.example.pidev.service.chat.ChatbotService;
import com.example.pidev.service.user.UserService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import javafx.scene.text.TextFlow;

public class ChatController {

    @FXML private VBox chatBox;
    @FXML private TextField inputField;
    @FXML private Button sendButton;
    @FXML private Button clearButton;
    @FXML private ScrollPane scrollPane;
    @FXML private Label statusIndicator;

    private ChatbotService chatbotService;
    private UserService userService;

    // État pour la confirmation de suppression
    private boolean awaitingDeleteConfirmation = false;
    private int pendingDeleteUserId = -1;
    private String pendingDeleteEmail = "";

    @FXML
    public void initialize() {
        chatbotService = new ChatbotService();
        userService = new UserService();

        // Configuration de l'envoi avec Entrée
        inputField.setOnAction(event -> handleSendMessage());

        // Auto-scroll vers le bas quand de nouveaux messages arrivent
        chatBox.heightProperty().addListener((observable, oldValue, newValue) ->
                scrollPane.setVvalue(1.0));

        // Message de bienvenue
        addWelcomeMessage();

        System.out.println("✅ ChatController initialisé");
    }

    private void addWelcomeMessage() {
        String welcome = "👋 Bonjour ! Je suis votre assistant de gestion. " +
                "Posez-moi des questions sur les utilisateurs !\n\n" +
                "📌 **Exemples de questions :**\n" +
                "• Combien d'utilisateurs sont inscrits ?\n" +
                "• Liste des administrateurs\n" +
                "• Nouveaux utilisateurs ce mois\n" +
                "• Rôle de sellamiarij7@gmail.com\n" +
                "• Supprimer utilisateur test@example.com\n" +
                "• Statistiques\n" +
                "• Aide";

        addMessageToChat("Assistant", welcome, "bot-message", Pos.CENTER_LEFT, "#F1F3F4");
    }

    @FXML
    private void handleSendMessage() {
        String userMessage = inputField.getText().trim();
        if (userMessage.isEmpty()) return;

        // Afficher le message de l'utilisateur
        addMessageToChat("Vous", userMessage, "user-message", Pos.CENTER_RIGHT, "#E3F2FD");
        inputField.clear();

        // Vérifier si on attend une confirmation
        if (awaitingDeleteConfirmation) {
            handleDeleteConfirmation(userMessage);
            return;
        }

        // Traiter la question via le service
        new Thread(() -> {
            try {
                String response = chatbotService.processQuestion(userMessage);

                // Vérifier si c'est une demande de confirmation
                if (response.startsWith("CONFIRM_DELETE:")) {
                    String[] parts = response.split(":");
                    pendingDeleteUserId = Integer.parseInt(parts[1]);
                    pendingDeleteEmail = parts[2];

                    Platform.runLater(() -> {
                        showDeleteConfirmation(pendingDeleteEmail);
                    });
                } else {
                    // Réponse normale
                    Platform.runLater(() -> {
                        addMessageToChat("Assistant", response, "bot-message", Pos.CENTER_LEFT, "#F1F3F4");
                    });
                }
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    addMessageToChat("Assistant", "❌ Désolé, une erreur s'est produite.",
                            "bot-message error", Pos.CENTER_LEFT, "#FFEBEE");
                });
            }
        }).start();
    }

    private void showDeleteConfirmation(String email) {
        awaitingDeleteConfirmation = true;

        String confirmMessage = "⚠️ Êtes-vous sûr de vouloir supprimer l'utilisateur **" + email + "** ?\n\n" +
                "Répondez par **oui** pour confirmer ou **non** pour annuler.";

        addMessageToChat("Assistant", confirmMessage, "bot-message warning", Pos.CENTER_LEFT, "#FFF3E0");
    }

    private void handleDeleteConfirmation(String userResponse) {
        String response = userResponse.toLowerCase().trim();

        if (response.equals("oui") || response.equals("o") || response.equals("yes")) {
            // Procéder à la suppression
            new Thread(() -> {
                boolean deleted = userService.deleteUser(pendingDeleteUserId);
                Platform.runLater(() -> {
                    if (deleted) {
                        addMessageToChat("Assistant", "✅ Utilisateur **" + pendingDeleteEmail + "** supprimé avec succès !",
                                "bot-message success", Pos.CENTER_LEFT, "#E8F5E8");
                    } else {
                        addMessageToChat("Assistant", "❌ Échec de la suppression. Vérifiez que l'utilisateur existe.",
                                "bot-message error", Pos.CENTER_LEFT, "#FFEBEE");
                    }
                });
            }).start();
        } else {
            addMessageToChat("Assistant", "❌ Suppression annulée.", "bot-message", Pos.CENTER_LEFT, "#F1F3F4");
        }

        // Réinitialiser l'état
        awaitingDeleteConfirmation = false;
        pendingDeleteUserId = -1;
        pendingDeleteEmail = "";
    }

    private void addMessageToChat(String sender, String message, String styleClass,
                                  Pos alignment, String color) {
        Platform.runLater(() -> {
            HBox messageBox = new HBox();
            messageBox.setAlignment(alignment);
            messageBox.setPadding(new Insets(5, 10, 5, 10));

            Label senderLabel = new Label(sender + ": ");
            senderLabel.setStyle("-fx-font-weight: bold; -fx-text-fill: #2196F3;");

            Text messageText = new Text(message);
            TextFlow messageFlow = new TextFlow(senderLabel, messageText);
            messageFlow.setStyle("-fx-background-color: " + color + "; -fx-background-radius: 15; -fx-padding: 10;");
            messageFlow.setMaxWidth(500);

            messageBox.getChildren().add(messageFlow);
            chatBox.getChildren().add(messageBox);

            // Faire défiler vers le bas
            scrollPane.setVvalue(1.0);
        });
    }

    @FXML
    private void handleSuggestion(javafx.event.ActionEvent event) {
        Button sourceButton = (Button) event.getSource();
        String suggestion = (String) sourceButton.getUserData();
        inputField.setText(suggestion);
        handleSendMessage();
    }

    @FXML
    private void handleClearChat() {
        chatBox.getChildren().clear();
        addWelcomeMessage();
    }
}