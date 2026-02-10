package com.example.pidev.controller.dashboard;

import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ProgressBar;
import javafx.stage.Stage;

import java.net.URL;
import java.time.LocalDate;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.ResourceBundle;

public class DashboardController implements Initializable {

    @FXML
    private Label dateLabel;

    @FXML
    private ProgressBar planifieProgress;

    @FXML
    private ProgressBar enCoursProgress;

    @FXML
    private ProgressBar termineProgress;

    @FXML
    private Button refreshBtn;

    @FXML
    private Button createEventBtn;

    @FXML
    private Button exportBtn;

    @FXML
    private Button reportBtn;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        System.out.println("Initialisation DashboardController...");

        // Debug : vérifier si les éléments sont null
        System.out.println("dateLabel: " + (dateLabel != null ? "OK" : "NULL"));
        System.out.println("planifieProgress: " + (planifieProgress != null ? "OK" : "NULL"));
        System.out.println("enCoursProgress: " + (enCoursProgress != null ? "OK" : "NULL"));
        System.out.println("termineProgress: " + (termineProgress != null ? "OK" : "NULL"));
        System.out.println("refreshBtn: " + (refreshBtn != null ? "OK" : "NULL"));

        setupDashboard();
        setupButtons();
    }

    private void setupDashboard() {
        // Vérifier d'abord si dateLabel n'est pas null
        if (dateLabel == null) {
            System.err.println("ERREUR: dateLabel est null !");
            return;
        }

        // Mettre à jour la date avec l'heure actuelle
        String currentDate = LocalDate.now().format(DateTimeFormatter.ofPattern("dd MMM yyyy"));
        String currentTime = LocalTime.now().format(DateTimeFormatter.ofPattern("HH:mm"));
        dateLabel.setText(currentDate + ", " + currentTime);

        // Configurer les progress bars (vérifier qu'ils ne sont pas null)
        if (planifieProgress != null) planifieProgress.setProgress(0.4);
        if (enCoursProgress != null) enCoursProgress.setProgress(0.3);
        if (termineProgress != null) termineProgress.setProgress(0.3);

        System.out.println("Dashboard configuré avec succès!");
    }

    private void setupButtons() {
        // Vérifier si les boutons existent avant d'ajouter des listeners
        if (refreshBtn != null) {
            refreshBtn.setOnAction(e -> {
                System.out.println("Actualisation du dashboard...");
                // Logique d'actualisation
                setupDashboard();
            });
        }

        if (createEventBtn != null) {
            createEventBtn.setOnAction(e -> {
                System.out.println("Création d'un nouvel événement...");
                // Naviguer vers la page de création d'événement
            });
        }

        if (exportBtn != null) {
            exportBtn.setOnAction(e -> {
                System.out.println("Exportation des données...");
                // Logique d'export
            });
        }

        if (reportBtn != null) {
            reportBtn.setOnAction(e -> {
                System.out.println("Génération du rapport...");
                // Logique de génération de rapport
            });
        }
    }
    public void goToLogin (ActionEvent actionEvent){
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/dashboard/dashboard.fxml"));
            Stage stage = (Stage) ((Node) actionEvent.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();
        }catch (Exception e){
            e.printStackTrace();
        }

    }
    public void goToGestionUser (ActionEvent actionEvent){
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/user/user.fxml"));
            Stage stage = (Stage) ((Node) actionEvent.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();
        }catch (Exception e){
            e.printStackTrace();
        }

    }

    public void goToProfil(ActionEvent event ) {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/user/profil.fxml"));
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();
        }catch (Exception e){
            e.printStackTrace();
        }
    }
    public void goToDashboard(ActionEvent event ) {
        try {
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/user/profil.fxml"));
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.setTitle("Login");
            stage.show();
        }catch (Exception e){
            e.printStackTrace();
        }
    }
}