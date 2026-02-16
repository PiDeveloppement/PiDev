package com.example.pidev;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.layout.StackPane;

import java.util.Objects;

public class MainLayoutController {

    @FXML private StackPane contentPane;

    @FXML private Button btnSponsors;
    @FXML private Button btnBudget;
    @FXML private Button btnDepenses;

    @FXML
    private void initialize() {
        // page par défaut
        showSponsors();
    }

    @FXML
    public void showSponsors() {
        setActive(btnSponsors);
        loadIntoCenter("/com/example/pidev/fxml/Sponsor/sponsor_portal.fxml");
    }

    @FXML
    public void showBudget() {
        setActive(btnBudget);
        loadIntoCenter("/com/example/pidev/fxml/Budget/budget.fxml");
    }

    @FXML
    public void showDepenses() {
        setActive(btnDepenses);
        loadIntoCenter("/com/example/pidev/fxml/Depense/depense.fxml");
    }

    private void loadIntoCenter(String fxmlPath) {
        try {
            Parent view = FXMLLoader.load(Objects.requireNonNull(getClass().getResource(fxmlPath)));
            contentPane.getChildren().setAll(view);
        } catch (Exception e) {
            throw new RuntimeException("Impossible de charger: " + fxmlPath, e);
        }
    }

    private void setActive(Button active) {
        // reset styles (simple)
        String normal = "-fx-background-color: rgba(255,255,255,0.95); -fx-text-fill: #0D47A1; "
                + "-fx-font-size: 14px; -fx-padding: 14 20; -fx-alignment: CENTER_LEFT; "
                + "-fx-border-radius: 8; -fx-background-radius: 8; -fx-min-width: 180;";

        String selected = "-fx-background-color: white; -fx-text-fill: #0D47A1; -fx-font-weight: bold; "
                + "-fx-font-size: 14px; -fx-padding: 14 20; -fx-alignment: CENTER_LEFT; "
                + "-fx-border-radius: 8; -fx-background-radius: 8; -fx-min-width: 180; "
                + "-fx-border-color: #64b5f6; -fx-border-width: 2;";

        if (btnSponsors != null) btnSponsors.setStyle(normal);
        if (btnBudget != null) btnBudget.setStyle(normal);
        if (btnDepenses != null) btnDepenses.setStyle(normal);

        if (active != null) active.setStyle(selected);
    }
}
