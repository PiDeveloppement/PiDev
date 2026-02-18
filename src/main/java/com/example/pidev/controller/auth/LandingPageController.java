package com.example.pidev.controller.auth;

import com.example.pidev.HelloApplication;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.animation.ScaleTransition;
import javafx.util.Duration;

import java.net.URL;
import java.util.ResourceBundle;

public class LandingPageController implements Initializable {

    @FXML private ScrollPane mainScrollPane;
    @FXML private VBox homeSection;
    @FXML private VBox featuresSection;
    @FXML private VBox contactSection;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        System.out.println("✅ LandingPageController initialisé");
    }

    @FXML
    private void scrollToTop() {
        if (mainScrollPane != null) {
            mainScrollPane.setVvalue(0);
        }
    }

    @FXML
    private void scrollToFeatures() {
        if (mainScrollPane != null && featuresSection != null) {
            // Calculer la position de la section (approximatif, à ajuster)
            mainScrollPane.setVvalue(0.35);
        }
    }

    @FXML
    private void scrollToContact() {
        if (mainScrollPane != null && contactSection != null) {
            mainScrollPane.setVvalue(0.75);
        }
    }

    @FXML
    private void handleLogin(ActionEvent event) {
        HelloApplication.loadLoginPage();
    }

    @FXML
    private void handleSignup(ActionEvent event) {
        HelloApplication.loadSignupPage();
    }

    // Animation simple au survol des cartes (optionnel)
    @FXML
    private void animateCard(javafx.scene.input.MouseEvent event) {
        StackPane card = (StackPane) event.getSource();
        ScaleTransition st = new ScaleTransition(Duration.millis(200), card);
        st.setToX(1.02);
        st.setToY(1.02);
        st.play();
    }

    @FXML
    private void resetCard(javafx.scene.input.MouseEvent event) {
        StackPane card = (StackPane) event.getSource();
        ScaleTransition st = new ScaleTransition(Duration.millis(200), card);
        st.setToX(1.0);
        st.setToY(1.0);
        st.play();
    }
}