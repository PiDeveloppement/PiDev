package com.melocode.pigestion.controller;

import com.melocode.pigestion.model.FeedbackStats;
import com.melocode.pigestion.service.FeedbackService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.layout.*;
import javafx.stage.Stage;
import java.util.List;

public class HistoriqueController {
    @FXML private FlowPane cardsContainer;
    private final FeedbackService fs = new FeedbackService();

    @FXML
    public void initialize() {
        refreshView();
    }

    public void refreshView() {
        try {
            cardsContainer.getChildren().clear();
            List<FeedbackStats> data = fs.recupererHistorique();

            for (FeedbackStats stats : data) {
                cardsContainer.getChildren().add(createTestimonialCard(stats));
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private VBox createTestimonialCard(FeedbackStats stats) {
        VBox card = new VBox(10);
        card.setPadding(new Insets(20));
        card.setPrefWidth(300);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 15; " +
                "-fx-effect: dropshadow(gaussian, rgba(0,0,0,0.1), 10, 0, 0, 5); -fx-cursor: hand;");

        Label lblUser = new Label(stats.getUsername().toUpperCase());
        lblUser.setStyle("-fx-font-weight: bold; -fx-font-size: 16; -fx-text-fill: #2c3e50;");

        HBox stars = new HBox(2);
        for (int i = 0; i < 5; i++) {
            Label star = new Label(i < stats.getEtoiles() ? "★" : "☆");
            star.setStyle("-fx-text-fill: #f1c40f; -fx-font-size: 20;");
            stars.getChildren().add(star);
        }

        Label lblComment = new Label(stats.getCommentaire());
        lblComment.setWrapText(true);
        lblComment.setStyle("-fx-text-fill: #7f8c8d; -fx-font-style: italic;");

        card.getChildren().addAll(lblUser, stars, lblComment);

        // CLIC : Redirection vers Resultat.fxml
        card.setOnMouseClicked(event -> {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/melocode.pigestion/fxml/Resultat.fxml"));
                Parent root = loader.load();
                Stage stage = (Stage) cardsContainer.getScene().getWindow();
                stage.setScene(new Scene(root));
            } catch (Exception e) {
                e.printStackTrace();
            }
        });

        return card;
    }
}