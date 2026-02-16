package com.melocode.pigestion.service;

import com.melocode.pigestion.model.Evenement;
import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.utils.MyConnection;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class QuestionService {
    private Connection conn = MyConnection.getInstance().getCnx();

    public List<Evenement> chargerEvenements() throws SQLException {
        List<Evenement> liste = new ArrayList<>();
        String req = "SELECT id_event, nom_event FROM events";
        Statement st = conn.createStatement();
        ResultSet rs = st.executeQuery(req);
        while (rs.next()) {
            liste.add(new Evenement(rs.getInt("id_event"), rs.getString("nom_event")));
        }
        return liste;
    }

    // Nouvelle méthode pour filtrer les questions par événement
    public List<Question> afficherParEvenement(int idEvent) throws SQLException {
        List<Question> liste = new ArrayList<>();
        String req = "SELECT * FROM questions WHERE id_event = ?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idEvent);
        ResultSet rs = pst.executeQuery();
        while (rs.next()) {
            liste.add(new Question(
                    rs.getInt("id_question"), rs.getInt("id_event"),
                    rs.getString("texte_question"), rs.getString("bonne_reponse"), rs.getInt("points")
            ));
        }
        return liste;
    }

    public void ajouter(Question q) throws SQLException {
        String req = "INSERT INTO questions (id_event, texte_question, bonne_reponse, points) VALUES (?, ?, ?, ?)";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, q.getIdEvent());
        pst.setString(2, q.getTexteQuestion());
        pst.setString(3, q.getBonneReponse());
        pst.setInt(4, q.getPoints());
        pst.executeUpdate();
    }

    public void modifier(Question q) throws SQLException {
        String req = "UPDATE questions SET id_event=?, texte_question=?, bonne_reponse=?, points=? WHERE id_question=?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, q.getIdEvent());
        pst.setString(2, q.getTexteQuestion());
        pst.setString(3, q.getBonneReponse());
        pst.setInt(4, q.getPoints());
        pst.setInt(5, q.getIdQuestion());
        pst.executeUpdate();
    }

    public void supprimer(int id) throws SQLException {
        // Supprimer d'abord les feedbacks (clé étrangère)
        PreparedStatement pstF = conn.prepareStatement("DELETE FROM feedbacks WHERE id_question = ?");
        pstF.setInt(1, id);
        pstF.executeUpdate();

        // Puis supprimer la question
        PreparedStatement pstQ = conn.prepareStatement("DELETE FROM questions WHERE id_question = ?");
        pstQ.setInt(1, id);
        pstQ.executeUpdate();
    }
}