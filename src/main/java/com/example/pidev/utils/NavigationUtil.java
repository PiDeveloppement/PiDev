package com.example.pidev.utils;

import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Node;
import javafx.stage.Stage;
import javafx.scene.Scene;

import java.io.IOException;

public class NavigationUtil {

    public static void goTo(Node anyNodeFromScene, String fxmlPath) {
        try {
            Parent root = FXMLLoader.load(NavigationUtil.class.getResource(fxmlPath));
            Stage stage = (Stage) anyNodeFromScene.getScene().getWindow();
            Scene scene = new Scene(root, anyNodeFromScene.getScene().getWidth(), anyNodeFromScene.getScene().getHeight());
            scene.getStylesheets().add(NavigationUtil.class.getResource("/com/example/pidev/css/atlantafx-custom.css").toExternalForm());
            stage.setScene(scene);
            stage.show();
        } catch (IOException e) {
            throw new RuntimeException("Navigation failed: " + fxmlPath, e);
        }
    }
}
