package com.melocode.pigestion.controller.auth;

import com.melocode.pigestion.HelloApplication;
import com.melocode.pigestion.utils.MyConnection;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.scene.paint.Color;
import javafx.scene.shape.Circle;

import java.net.URL;
import java.sql.*;
import java.util.ResourceBundle;

public class LandingPageController implements Initializable {

    @FXML private StackPane contentArea;

    // Couleurs du design (Violet moderne)
    private final String PRIMARY_PURPLE = "#4f46e5";
    private final String LIGHT_PURPLE_BG = "#f5f3ff";
    private final String TEXT_DARK = "#1e1b4b";
    private final String TEXT_GRAY = "#64748b";

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        // Chargement initial
        handleFeedback(null);
    }

    @FXML
    private void handleFeedback(ActionEvent event) {
        contentArea.getChildren().clear();
        VBox mainContainer = new VBox(40);
        mainContainer.setPadding(new Insets(50, 100, 50, 100));
        mainContainer.setAlignment(Pos.TOP_CENTER);
        mainContainer.setStyle("-fx-background-color: white;");

        // 1. Header Statistiques
        mainContainer.getChildren().add(createSummaryHeader());

        // 2. Titre
        HBox titleRow = new HBox();
        titleRow.setAlignment(Pos.CENTER_LEFT);
        Label lblRecent = new Label("Recent Feedbacks");
        lblRecent.setStyle("-fx-font-size: 26px; -fx-font-weight: bold; -fx-text-fill: " + TEXT_DARK + ";");
        titleRow.getChildren().add(lblRecent);
        mainContainer.getChildren().add(titleRow);

        // 3. Grille de cartes
        FlowPane feedbackGrid = new FlowPane();
        feedbackGrid.setHgap(30);
        feedbackGrid.setVgap(30);
        feedbackGrid.setAlignment(Pos.CENTER);

        loadFeedbacksWithEventsFromDB(feedbackGrid);

        mainContainer.getChildren().add(feedbackGrid);

        // 4. Pagination
        mainContainer.getChildren().add(createPaginationBar());

        ScrollPane scrollPane = new ScrollPane(mainContainer);
        scrollPane.setFitToWidth(true);
        scrollPane.setStyle("-fx-background-color: white; -fx-background: white; -fx-border-color: transparent;");
        contentArea.getChildren().setAll(scrollPane);
    }

    private void loadFeedbacksWithEventsFromDB(FlowPane grid) {
        // JOINTURE SQL pour prendre le nom dans la table 'events'
        String query = "SELECT f.id_user, f.comments, f.etoiles, e.nom_event " +
                "FROM feedbacks f " +
                "INNER JOIN events e ON f.id_event = e.id_event " +
                "WHERE f.comments IS NOT NULL AND f.comments != '****' " +
                "ORDER BY f.id_feedback DESC LIMIT 4";

        try {
            Connection conn = MyConnection.getInstance().getCnx();

            // Verification de la connexion pour eviter l'erreur "Connection Closed"
            if (conn == null || conn.isClosed()) {
                System.out.println("⚠️ Connexion SQL fermée, vérifiez votre classe MyConnection.");
                return;
            }

            // On ne ferme pas la Connection ici (Singleton), seulement Statement et ResultSet
            try (Statement stmt = conn.createStatement();
                 ResultSet rs = stmt.executeQuery(query)) {

                while (rs.next()) {
                    String user = "Utilisateur #" + rs.getInt("id_user");
                    String event = rs.getString("nom_event"); // Prend le nom dans la table events
                    String msg = rs.getString("comments");
                    int note = rs.getInt("etoiles");

                    grid.getChildren().add(createFeedbackCard(user, event, msg, note));
                }
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    private VBox createFeedbackCard(String userName, String eventName, String comment, int rating) {
        VBox card = new VBox(15);
        card.setPrefWidth(520);
        card.setPadding(new Insets(35));
        card.setStyle("-fx-background-color: white; -fx-background-radius: 25; -fx-border-color: #f1f5f9; -fx-border-width: 1.5; " +
                "-fx-effect: dropshadow(gaussian, rgba(0,0,0,0.02), 30, 0, 0, 15);");

        // Etoiles
        HBox stars = new HBox(5);
        for(int i=0; i<5; i++) {
            Label s = new Label(i < rating ? "★" : "★");
            s.setStyle("-fx-text-fill: " + (i < rating ? "#f59e0b" : "#e2e8f0") + "; -fx-font-size: 22px;");
            stars.getChildren().add(s);
        }

        // Profil + Nom Event
        HBox profile = new HBox(15);
        profile.setAlignment(Pos.CENTER_LEFT);
        Circle avatar = new Circle(22, Color.web("#e2e8f0"));
        VBox info = new VBox(2);
        Label n = new Label(userName);
        n.setStyle("-fx-font-weight: bold; -fx-font-size: 17px; -fx-text-fill: " + TEXT_DARK + ";");

        Label ev = new Label("📅 " + eventName); // Affiche le nom réel de l'event
        ev.setStyle("-fx-text-fill: " + PRIMARY_PURPLE + "; -fx-font-weight: bold; -fx-font-size: 13px;");

        info.getChildren().addAll(n, ev);
        profile.getChildren().addAll(avatar, info);

        Label c = new Label(comment);
        c.setWrapText(true);
        c.setStyle("-fx-text-fill: " + TEXT_GRAY + "; -fx-font-size: 15px; -fx-line-spacing: 5;");
        c.setMinHeight(80);

        card.getChildren().addAll(stars, profile, c);
        return card;
    }

    private VBox createSummaryHeader() {
        HBox header = new HBox(100);
        header.setAlignment(Pos.CENTER);
        header.setPadding(new Insets(20, 0, 40, 0));

        VBox left = new VBox(15);
        String[] labels = {"5 ★", "4 ★", "3 ★", "2 ★", "1 ★"};
        double[] vals = {0.4, 0.85, 0.15, 0.08, 0.05};
        for(int i=0; i<5; i++){
            HBox r = new HBox(10);
            r.setAlignment(Pos.CENTER_LEFT);
            ProgressBar pb = new ProgressBar(vals[i]);
            pb.setPrefWidth(200);
            pb.setStyle("-fx-accent: " + PRIMARY_PURPLE + ";");
            r.getChildren().addAll(new Label(labels[i]), pb);
            left.getChildren().add(r);
        }

        VBox right = new VBox(15);
        Label t = new Label("Ratings & Feedback");
        t.setStyle("-fx-font-size: 32px; -fx-font-weight: 900;");
        Label d = new Label("Retrouvez ici les avis de nos participants sur les événements organisés.");
        d.setStyle("-fx-text-fill: " + TEXT_GRAY + ";");
        right.getChildren().addAll(t, d);

        header.getChildren().addAll(left, right);
        return new VBox(header);
    }

    private HBox createPaginationBar() {
        HBox bar = new HBox(10);
        bar.setAlignment(Pos.CENTER);
        Button b1 = new Button("1");
        Button b2 = new Button("2");
        b2.setStyle("-fx-background-color: "+PRIMARY_PURPLE+"; -fx-text-fill: white; -fx-background-radius: 50;");
        bar.getChildren().addAll(new Button("<"), b1, b2, new Button(">"));
        return bar;
    }

    // --- METHODES POUR EVITER LES ERREURS FXML ---
    @FXML private void handleEvenement(ActionEvent e) {}
    @FXML private void handleQuestionnaire(ActionEvent e) {}
    @FXML private void handleContact(ActionEvent e) {}
    @FXML private void handleLogin(ActionEvent e) { HelloApplication.loadLoginPage(); }
    @FXML private void handleSignup(ActionEvent e) { HelloApplication.loadSignupPage(); }
}