#!/bin/bash

# =============================================================================
# 🚀 STIQRHUB Frontend Docker Management Script - Enhanced Version
# =============================================================================
# Usage:
#   ./docker-start.sh prod deploy   # Build & run production
#   ./docker-start.sh dev run       # Build & run development
#   ./docker-start.sh prod build    # Build only
#   ./docker-start.sh stop          # Stop all containers
#   ./docker-start.sh clean         # Clean images & containers
#   ./docker-start.sh logs          # Show container logs
#   ./docker-start.sh status        # Show status
# =============================================================================

set -e  # Exit on any error

# 🎨 Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 📦 Configuration
IMAGE_NAME="stiqrhub-fe"
CONTAINER_NAME="stiqrhub-frontend"
DOCKER_REGISTRY="dns-regx.dnstech.co.id"

# 🔧 Functions
print_usage() {
    echo -e "${BLUE}==============================================================================${NC}"
    echo -e "${BLUE}🚀 STIQRHUB Frontend Docker Management Script - Enhanced${NC}"
    echo -e "${BLUE}==============================================================================${NC}"
    echo ""
    echo -e "${YELLOW}Usage: ./docker-start.sh [environment] [action]${NC}"
    echo ""
    echo -e "${YELLOW}Environments:${NC}"
    echo -e "  ${GREEN}prod, production${NC}     # Production environment (port 80)"
    echo -e "  ${GREEN}dev, development${NC}     # Development environment (port 3000)"
    echo -e "  ${GREEN}staging${NC}             # Staging environment (port 8080)"
    echo ""
    echo -e "${YELLOW}Actions:${NC}"
    echo -e "  ${GREEN}deploy${NC}              # Clean + Build + Run (default)"
    echo -e "  ${GREEN}build${NC}               # Build Docker image only"
    echo -e "  ${GREEN}build-simple${NC}        # Build using simple Dockerfile"
    echo -e "  ${GREEN}run${NC}                 # Run container only"
    echo -e "  ${GREEN}stop${NC}                # Stop container"
    echo -e "  ${GREEN}clean${NC}               # Clean images & containers"
    echo -e "  ${GREEN}logs${NC}                # Show container logs"
    echo -e "  ${GREEN}status${NC}              # Show status"
    echo ""
    echo -e "${YELLOW}Examples:${NC}"
    echo -e "  ${GREEN}./docker-start.sh prod deploy${NC}    # Full production deployment"
    echo -e "  ${GREEN}./docker-start.sh dev build${NC}      # Build dev image only"
    echo -e "  ${GREEN}./docker-start.sh staging run${NC}    # Run staging container"
    echo ""
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# 🏗️ Build Docker Image
build_image() {
    local env=${1:-prod}
    local dockerfile=${2:-Dockerfile}

    print_step "Building Docker image for $env environment..."
    print_step "Using dockerfile: $dockerfile"

    # Stop existing container if running
    if docker ps -q -f name=$CONTAINER_NAME | grep -q .; then
        print_warning "Stopping existing container..."
        docker stop $CONTAINER_NAME || true
        docker rm $CONTAINER_NAME || true
    fi

    # Build image with fallback options
    if [ "$env" = "dev" ]; then
        docker build -t $IMAGE_NAME:dev -f $dockerfile . || {
            print_error "Development build failed! Trying with simple dockerfile..."
            docker build -t $IMAGE_NAME:dev -f Dockerfile.simple . || {
                print_error "Simple build also failed! Falling back to production build..."
                docker build -t $IMAGE_NAME:latest -f $dockerfile .
            }
        }
    else
        docker build -t $IMAGE_NAME:latest -f $dockerfile . || {
            print_error "Build failed! Trying with simple DOCTYPE..."
            docker build -t $IMAGE_NAME:latest -f Dockerfile.simple .
        }
    fi

    print_success "Docker image built successfully!"
}

# 🚀 Run Container
run_container() {
    local env=${1:-prod}
    local port=80
    local image_tag="latest"

    if [ "$env" = "dev" ]; then
        port=3000
        image_tag="dev"
    fi

    print_step "Starting container on port $port..."

    # Stop existing container if running
    docker stop $CONTAINER_NAME 2>/dev/null || true
    docker rm $CONTAINER_NAME 2>/dev/null || true

    # Run container
    docker run -d \
        --name $CONTAINER_NAME \
        -p $port:80 \
        --restart unless-stopped \
        $IMAGE_NAME:$image_tag

    print_success "Container started successfully!"
    print_success "🌐 Access your app at: http://localhost:$port"

    # Show container status
    echo ""
    docker ps -f name=$CONTAINER_NAME --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
}

# 📊 Show Logs
show_logs() {
    print_step "Showing container logs..."
    if docker ps -q -f name=$CONTAINER_NAME | grep -q .; then
        docker logs -f $CONTAINER_NAME
    else
        print_error "Container $CONTAINER_NAME is not running!"
        exit 1
    fi
}

# 🛑 Stop Container
stop_container() {
    print_step "Stopping container..."
    docker stop $CONTAINER_NAME 2>/dev/null || print_warning "Container was not running"
    docker rm $CONTAINER_NAME 2>/dev/null || print_warning "Container was already removed"
    print_success "Container stopped and removed!"
}

# 🧹 Clean Up
clean_up() {
    print_step "Cleaning up Docker resources..."

    # Stop and remove container
    docker stop $CONTAINER_NAME 2>/dev/null || true
    docker rm $CONTAINER_NAME 2>/dev/null || true

    # Remove images
    docker rmi $IMAGE_NAME:latest 2>/dev/null || true
    docker rmi $IMAGE_NAME:dev 2>/dev/null || true

    # Clean up dangling images
    docker image prune -f

    print_success "Cleanup completed!"
}

# 🔍 Check Docker Status
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed!"
        exit 1
    fi

    if ! docker info &> /dev/null; then
        print_error "Docker daemon is not running!"
        exit 1
    fi
}

# 📊 Show Status
show_status() {
    print_step "Current Docker Status:"
    echo ""
    echo -e "${YELLOW}🐳 Containers:${NC}"
    docker ps -a -f name=$CONTAINER_NAME --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" || echo "No containers found"
    echo ""
    echo -e "${YELLOW}🖼️ Images:${NC}"
    docker images $IMAGE_NAME --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}" || echo "No images found"
}

# 🎬 Main Script Logic
main() {
    local env=${1:-prod}
    local action=${2:-deploy}

    # Handle legacy single parameter usage
    case $env in
        "help"|"-h"|"--help"|"stop"|"clean"|"logs"|"status")
            action=$env
            env="prod"
            ;;
    esac

    # Normalize environment names
    case $env in
        "production") env="prod" ;;
        "development") env="dev" ;;
    esac

    # Check if Docker is available
    check_docker

    case $action in
        "deploy")
            print_step "🚀 Starting $env deployment (clean + build + run)..."
            clean_up
            build_image $env
            run_container $env
            show_status
            ;;
        "build")
            print_step "🏗️ Building image for $env environment..."
            build_image $env
            ;;
        "build-simple")
            print_step "🏗️ Building image for $env environment using simple Dockerfile..."
            build_image $env "Dockerfile.simple"
            ;;
        "run")
            print_step "▶️ Running container for $env environment..."
            run_container $env
            ;;
        "stop")
            stop_container
            ;;
        "clean")
            clean_up
            ;;
        "logs")
            show_logs
            ;;
        "status")
            show_status
            ;;
        "help"|"-h"|"--help")
            print_usage
            ;;
        *)
            print_error "Unknown action: $action"
            print_usage
            exit 1
            ;;
    esac
}

# 🎯 Execute main function
main "$@"
