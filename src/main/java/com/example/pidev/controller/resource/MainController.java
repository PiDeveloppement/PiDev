package com.example.pidev.controller.resource;

import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.layout.StackPane;
import java.io.IOException;
import java.net.URL;
import java.util.Arrays;
import java.util.List;

public class MainController {
    @FXML private StackPane contentArea;

    // On ajoute les IDs des boutons pour gérer le style "Actif"
    @FXML private Button btnReservation, btnSalles, btnEquipements;

    private static MainController instance;

    public MainController() {
        instance = this;
    }

    public static MainController getInstance() {
        return instance;
    }

    @FXML
    public void initialize() {
        // Par défaut, on affiche les salles et on active le bouton correspondant
        showSalles();
    }

    /**
     * Gère le style visuel des boutons de la sidebar
     */
    private void setActiveButton(Button activeBtn) {
        List<Button> allButtons = Arrays.asList(btnReservation, btnSalles, btnEquipements);

        for (Button btn : allButtons) {
            if (btn == null) continue;

            // On retire la classe active de tous les boutons
            btn.getStyleClass().remove("sidebar-button-active");

            // On s'assure qu'ils ont tous la classe de base
            if (!btn.getStyleClass().contains("sidebar-button")) {
                btn.getStyleClass().add("sidebar-button");
            }
        }
        // On ajoute la classe active uniquement au bouton cliqué
        activeBtn.getStyleClass().add("sidebar-button-active");
    }

    public void loadPage(String fxml) {
        try {
            String path = "/com/example/pidev/fxml/resource/" + fxml;
            URL url = getClass().getResource(path);

            if (url == null) {
                System.err.println("ERREUR : Fichier FXML introuvable : " + path);
                return;
            }

            FXMLLoader loader = new FXMLLoader(url);
            Parent root = loader.load();

            contentArea.getChildren().setAll(root);

        } catch (IOException e) {
            System.err.println("ERREUR lors du chargement de la page : " + fxml);
            e.printStackTrace();
        }
    }

    @FXML
    public void showReservation() {
        loadPage("reservation.fxml");
        setActiveButton(btnReservation);
    }

    @FXML
    public void showSalles() {
        loadPage("salle.fxml");
        setActiveButton(btnSalles);
    }

    @FXML
    public void showEquipements() {
        loadPage("equipement.fxml");
        setActiveButton(btnEquipements);
    }
}