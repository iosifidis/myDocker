# Use the official Node.js LTS (Long Term Support) image on Alpine Linux as the base image
FROM node:14-alpine AS build

# Set the working directory inside the container
WORKDIR /app

# Install git in the image
RUN apk add --no-cache git

# Clone the repository into the working directory
RUN git clone https://gitlab.com/opensourceuom/myUoM .

# Install the project dependencies
RUN npm install

# Build the React app for production
RUN npm run build

# Use the lightweight Nginx image on Alpine Linux to serve the built React app
FROM nginx:alpine

# Copy the built React app from the 'build' stage to the Nginx public directory
COPY --from=build /app/build /usr/share/nginx/html

# Expose port 80 to the host machine
EXPOSE 80

# Start Nginx when the container runs
CMD ["nginx", "-g", "daemon off;"]

