package com.melocode.pigestion;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.io.IOException;

public class HelloApplication extends Application {
    @Override
    public void start(Stage stage) throws IOException {
        // Charge le Dashboard
        FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("fxml/Dashboard.fxml"));
        Scene scene = new Scene(fxmlLoader.load());

        stage.setTitle("PI-GESTION v3.0");
        stage.setScene(scene);
        stage.show();
    }

    public static void main(String[] args) {
        launch();
    }
}