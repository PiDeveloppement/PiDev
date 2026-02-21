package com.example.pidev.model.questionnaire;

public class Question {
    private int idQuestion;
    private int idEvent;
    private String texte;
    private String reponse;
    private int points;
    private String nomEvent; // Harmonisé avec le service

    public Question(int idQuestion, int idEvent, String texte, String reponse, int points) {
        this.idQuestion = idQuestion;
        this.idEvent = idEvent;
        this.texte = texte;
        this.reponse = reponse;
        this.points = points;
    }

    // Getters
    public int getIdQuestion() { return idQuestion; }
    public int getIdEvent() { return idEvent; }
    public String getTexte() { return texte; } // Utilisé par les getters ci-dessous si nécessaire
    public String getReponse() { return reponse; }
    public int getPoints() { return points; }
    public String getNomEvent() { return nomEvent; }

    // Setters
    public void setIdQuestion(int idQuestion) { this.idQuestion = idQuestion; }
    public void setIdEvent(int idEvent) { this.idEvent = idEvent; }
    public void setTexte(String texte) { this.texte = texte; }
    public void setReponse(String reponse) { this.reponse = reponse; }
    public void setPoints(int points) { this.points = points; }
    public void setNomEvent(String nomEvent) { this.nomEvent = nomEvent; }

    // Méthodes de compatibilité pour le Service (getTexteQuestion et getBonneReponse)
    public String getTexteQuestion() { return texte; }
    public String getBonneReponse() { return reponse; }
}