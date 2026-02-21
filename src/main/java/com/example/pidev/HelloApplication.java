package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.net.URL;
import java.util.Objects;

public class HelloApplication extends Application {

    @Override
    public void start(Stage stage) throws Exception {
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        URL fxml = getClass().getResource("/com/example/pidev/fxml/MainLayout.fxml");
        System.out.println("FXML URL = " + fxml);

        FXMLLoader loader = new FXMLLoader(fxml);
        Parent root = loader.load();

        // ✅ Stocker MainController dans root.userData (pour y accéder depuis tous les controllers)
        MainController mainController = loader.getController();
        root.setUserData(mainController);

        Scene scene = new Scene(root, 1200, 800);
        scene.getStylesheets().add(
                Objects.requireNonNull(getClass().getResource("/com/example/pidev/css/atlantafx-custom.css"))
                        .toExternalForm()
        );

        stage.setTitle("EventFlow");
        stage.setScene(scene);
        stage.setMinWidth(1000);
        stage.setMinHeight(700);
        stage.show();

        // ✅ optionnel : charger une page par défaut (ex: sponsors)
        // mainController.onSponsorsList();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
