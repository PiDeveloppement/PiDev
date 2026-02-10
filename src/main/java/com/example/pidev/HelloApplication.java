package com.example.pidev;

import atlantafx.base.theme.PrimerDark;
import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

public class HelloApplication extends Application {

    @Override
    public void start(Stage stage) throws Exception {

        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));

        stage.initStyle(StageStyle.DECORATED);


        // 3. Créer la scène avec une taille appropriée
        Scene scene = new Scene(root, 1200, 800);

        // Charger le fichier CSS
        scene.getStylesheets().add(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());


        stage.setTitle("EventFlow - Inscription");
        stage.setScene(scene);
        stage.setMinWidth(1000);
        stage.setMinHeight(700);
        stage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}