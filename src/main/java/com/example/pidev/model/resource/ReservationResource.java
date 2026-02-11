package com.example.pidev.model.resource;

import java.time.LocalDateTime;

public class ReservationResource {
    private int id;
    private String resourceType, resourceName, imagePath;
    private Integer salleId, equipementId;
    private LocalDateTime startTimedate, endTime;
    private int quantity;

    public ReservationResource(int id, String resourceType, Integer salleId, Integer equipementId,
                               LocalDateTime startTimedate, LocalDateTime endTime, int quantity) {
        this.id = id;
        this.resourceType = resourceType;
        this.salleId = salleId;
        this.equipementId = equipementId;
        this.startTimedate = startTimedate;
        this.endTime = endTime;
        this.quantity = quantity;
    }

    // Getters
    public int getId() { return id; }
    public String getResourceType() { return resourceType; }
    public Integer getSalleId() { return salleId; }
    public Integer getEquipementId() { return equipementId; }
    public LocalDateTime getStartTimedate() { return startTimedate; }
    public LocalDateTime getEndTime() { return endTime; }
    public int getQuantity() { return quantity; }
    public String getResourceName() { return resourceName; }
    public String getImagePath() { return imagePath; }

    // Setters pour les données jointes
    public void setResourceName(String name) { this.resourceName = name; }
    public void setImagePath(String path) { this.imagePath = path; }
}